<?php

namespace App\Services;

use App\Models\PackageSetting;
use App\Models\PremiumFeatureUsageLog;
use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Models\TenantResourceConsume;
use App\Support\PackageQuota;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 套餐订购 + 财务成本台账
 */
class FinanceService
{
    /** 成本单价假设（演示用，可后续做成配置） */
    public const COST_AI_PER_CALL = 0.008;
    public const COST_PROXY_PER_REQ = 0.0012;
    public const COST_SERVER_PER_TENANT_DAY = 1.5;
    public const COST_THIRD_PARTY_PER_TENANT_DAY = 0.8;
    public const COST_OPS_PER_TENANT_DAY = 2.0;

    public function catalog(): array
    {
        return PackageSetting::query()
            ->orderBy('package_type')
            ->get()
            ->map(fn (PackageSetting $s) => $s->toFrontendArray())
            ->values()
            ->all();
    }

    /** 租户购买 / 升级 / 降级 / 续费套餐（演示即时支付） */
    public function purchase(int $tenantId, string $packageCode, int $months = 1): TenantOrder
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $setting = PackageSetting::findByPackageCode($packageCode);
        if (!$setting) {
            throw new RuntimeException('套餐不存在');
        }

        $fromCode = (string) $tenant->package;
        $fromType = PackageSetting::typeFromPackageCode($fromCode);
        $toType = PackageSetting::typeFromPackageCode($packageCode);
        $change = match (true) {
            $toType > $fromType => 'upgrade',
            $toType < $fromType => 'downgrade',
            default => 'renew',
        };
        $changeLabel = match ($change) {
            'upgrade' => '升级',
            'downgrade' => '降级',
            default => '续费',
        };

        $months = max(1, min(12, $months));
        $price = (int) ($setting->price_monthly ?? 0);
        $amount = $price * $months;
        $starts = now();
        $expires = now()->addMonths($months)->endOfDay();

        $order = TenantOrder::create([
            'tenant_id' => $tenantId,
            'order_no' => 'PO'.now()->format('YmdHis').Str::upper(Str::random(4)),
            'package_code' => $packageCode,
            'package_version' => PackageSetting::PACKAGE_VERSION,
            'price_monthly' => $price,
            'months' => $months,
            'amount' => $amount,
            'status' => 'paid',
            'starts_at' => $starts,
            'expires_at' => $expires,
            'remark' => "套餐{$changeLabel}：{$fromCode} → {$packageCode} · 平台公共代理托管 · 禁止自有IP",
        ]);

        $defaults = match ($packageCode) {
            'pro' => ['concurrent' => 20, 'ai_quota' => 8000, 'binds' => 15, 'kb' => 10],
            'ent' => ['concurrent' => 50, 'ai_quota' => 999999, 'binds' => 999, 'kb' => 50],
            default => ['concurrent' => 5, 'ai_quota' => 800, 'binds' => 3, 'kb' => 1],
        };

        $tenant->update([
            'package' => $packageCode,
            'package_version' => PackageSetting::PACKAGE_VERSION,
            'package_expires_at' => $expires,
            'allow_self_proxy' => 0,
            'max_proxy_ip' => $setting->max_proxy_ip,
            'daily_proxy_request_limit' => $setting->daily_proxy_request_limit,
            // 降级时关闭更高档专属能力
            'dedicated_ip_pool_enabled' => (int) ($setting->enable_dedicated_ip_pool ?? 0) === 1
                ? (int) ($tenant->dedicated_ip_pool_enabled ?? 0)
                : 0,
            'ip_rotate_enabled' => (int) ($setting->enable_ip_rotate ?? 0) === 1
                ? (int) ($tenant->ip_rotate_enabled ?? 0)
                : 0,
            'white_label_title' => (int) ($setting->enable_white_label ?? 0) === 1
                ? $tenant->white_label_title
                : null,
            ...$defaults,
        ]);

        $fresh = $tenant->fresh();
        PackageQuota::reconcileTenantAiTemplate($fresh);
        $paused = PackageQuota::reconcileCrawlerTasksOnDowngrade($fresh);

        PremiumFeatureUsageLog::create([
            'tenant_id' => $tenantId,
            'feature_key' => 'package_'.$change,
            'feature_label' => '套餐'.$changeLabel,
            'detail' => sprintf(
                '%s → %s × %d月%s',
                PackageSetting::LABEL_MAP[$fromType] ?? $fromCode,
                $setting->toFrontendArray()['packageLabel'],
                $months,
                $paused > 0 ? "；已自动暂停超额爬虫 {$paused} 个" : ''
            ),
            'used_at' => now(),
        ]);

        $order->setAttribute('change_type', $change);
        $order->setAttribute('paused_crawler_count', $paused);

        return $order->fresh('tenant');
    }

    public function orders(?int $scopeTenantId = null): array
    {
        $q = TenantOrder::query()->with('tenant')->orderByDesc('id');
        if ($scopeTenantId) {
            $q->where('tenant_id', $scopeTenantId);
        }

        return $q->limit(200)->get()->map->toFrontendArray()->values()->all();
    }

    /**
     * 每日凌晨汇总：公共 IP / AI / 五项硬性成本 + 营收摊销
     */
    public function aggregateDailyCosts(?string $date = null): int
    {
        $day = $date ? \Carbon\Carbon::parse($date)->startOfDay() : now()->subDay()->startOfDay();
        $dateStr = $day->format('Y-m-d');
        $count = 0;

        Tenant::query()->where('status', 1)->orderBy('id')->each(function (Tenant $tenant) use ($dateStr, $day, &$count) {
            $ymd = str_replace('-', '', $dateStr);
            $proxyUsed = (int) Cache::get('tenant_proxy_req:'.$tenant->id.':'.$ymd, 0);
            if ($proxyUsed === 0 && $dateStr === now()->format('Y-m-d')) {
                $proxyUsed = PackageQuota::dailyProxyRequestUsed((int) $tenant->id);
            }
            $aiUsed = (int) Cache::get('tenant_ai_req:'.$tenant->id.':'.$ymd, 0);
            if ($aiUsed === 0 && $dateStr === now()->format('Y-m-d')) {
                $aiUsed = PackageQuota::dailyAiUsed((int) $tenant->id);
            }

            $setting = PackageQuota::settingForTenant($tenant);
            $price = (int) ($setting->price_monthly ?? 0);
            $revenue = round($price / max(1, (int) $day->daysInMonth), 2);

            TenantResourceConsume::query()->updateOrCreate(
                ['stat_date' => $dateStr, 'tenant_id' => $tenant->id],
                [
                    'package_code' => $tenant->package,
                    'proxy_request_count' => $proxyUsed,
                    'ai_call_count' => $aiUsed,
                    'ai_token_cost' => round($aiUsed * self::COST_AI_PER_CALL, 2),
                    'proxy_ip_cost' => round($proxyUsed * self::COST_PROXY_PER_REQ, 2),
                    'server_cost' => self::COST_SERVER_PER_TENANT_DAY,
                    'third_party_cost' => self::COST_THIRD_PARTY_PER_TENANT_DAY,
                    'ops_labor_cost' => self::COST_OPS_PER_TENANT_DAY,
                    'revenue' => $revenue,
                ]
            );
            $count++;
        });

        return $count;
    }

    /** 超管财务报表总览 */
    public function overview(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: now()->subDays(29)->format('Y-m-d');
        $to = $to ?: now()->format('Y-m-d');

        $rows = TenantResourceConsume::query()
            ->whereNotNull('tenant_id')
            ->whereBetween('stat_date', [$from, $to])
            ->get();

        if ($rows->isEmpty()) {
            // 无历史则即时生成今日快照便于演示
            $this->aggregateDailyCosts(now()->format('Y-m-d'));
            $rows = TenantResourceConsume::query()
                ->whereNotNull('tenant_id')
                ->whereBetween('stat_date', [$from, $to])
                ->get();
        }

        $revenue = (float) $rows->sum('revenue');
        $aiCost = (float) $rows->sum('ai_token_cost');
        $proxyCost = (float) $rows->sum('proxy_ip_cost');
        $serverCost = (float) $rows->sum('server_cost');
        $thirdCost = (float) $rows->sum('third_party_cost');
        $opsCost = (float) $rows->sum('ops_labor_cost');
        $totalCost = $aiCost + $proxyCost + $serverCost + $thirdCost + $opsCost;
        $net = $revenue - $totalCost;

        $byPackage = [];
        foreach (['basic', 'pro', 'ent'] as $code) {
            $pkgRows = $rows->where('package_code', $code);
            $r = (float) $pkgRows->sum('revenue');
            $byPackage[] = [
                'packageCode' => $code,
                'packageLabel' => PackageSetting::LABEL_MAP[PackageSetting::typeFromPackageCode($code)] ?? $code,
                'revenue' => round($r, 2),
                'share' => $revenue > 0 ? round($r / $revenue * 100, 1) : 0,
            ];
        }

        // 趋势：按日聚合
        $trendMap = [];
        foreach ($rows as $row) {
            $d = optional($row->stat_date)->format('Y-m-d');
            if (!$d) {
                continue;
            }
            if (!isset($trendMap[$d])) {
                $trendMap[$d] = ['date' => $d, 'revenue' => 0, 'cost' => 0, 'profit' => 0];
            }
            $c = $row->totalCost();
            $trendMap[$d]['revenue'] += (float) $row->revenue;
            $trendMap[$d]['cost'] += $c;
            $trendMap[$d]['profit'] += (float) $row->revenue - $c;
        }
        ksort($trendMap);
        $trend = array_values(array_map(function ($t) {
            return [
                'date' => $t['date'],
                'revenue' => round($t['revenue'], 2),
                'cost' => round($t['cost'], 2),
                'profit' => round($t['profit'], 2),
            ];
        }, $trendMap));

        return [
            'from' => $from,
            'to' => $to,
            'totalRevenue' => round($revenue, 2),
            'proxyIpCost' => round($proxyCost, 2),
            'aiTokenCost' => round($aiCost, 2),
            'serverCost' => round($serverCost, 2),
            'thirdPartyCost' => round($thirdCost, 2),
            'opsLaborCost' => round($opsCost, 2),
            'totalCost' => round($totalCost, 2),
            'netProfit' => round($net, 2),
            'grossMargin' => $revenue > 0 ? round($net / $revenue * 100, 1) : 0,
            'revenueByPackage' => $byPackage,
            'trend' => $trend,
            'note' => '全部爬虫 IP 消耗计入平台可变成本（公共住宅代理）',
        ];
    }

    public function consumeDetails(?int $scopeTenantId = null, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?: now()->subDays(29)->format('Y-m-d');
        $to = $to ?: now()->format('Y-m-d');
        $q = TenantResourceConsume::query()->with('tenant')
            ->whereBetween('stat_date', [$from, $to])
            ->orderByDesc('stat_date');
        if ($scopeTenantId) {
            $q->where('tenant_id', $scopeTenantId);
        } else {
            $q->whereNotNull('tenant_id');
        }

        return $q->limit(500)->get()->map->toFrontendArray()->values()->all();
    }

    public function premiumUsageLogs(?int $scopeTenantId = null): array
    {
        $q = PremiumFeatureUsageLog::query()->with('tenant')->orderByDesc('id');
        if ($scopeTenantId) {
            $q->where('tenant_id', $scopeTenantId);
        }

        return $q->limit(200)->get()->map->toFrontendArray()->values()->all();
    }

    public function exportConsumeCsv(?int $scopeTenantId = null, ?string $from = null, ?string $to = null): string
    {
        $rows = $this->consumeDetails($scopeTenantId, $from, $to);
        $lines = ['日期,租户,套餐,公共IP请求,AI调用,AI成本,代理成本,服务器,第三方,运维,总成本,营收,净利润,毛利率%'];
        foreach ($rows as $r) {
            $lines[] = implode(',', [
                $r['statDate'], $r['tenant'], $r['packageCode'],
                $r['proxyRequestCount'], $r['aiCallCount'],
                $r['aiTokenCost'], $r['proxyIpCost'], $r['serverCost'],
                $r['thirdPartyCost'], $r['opsLaborCost'], $r['totalCost'],
                $r['revenue'], $r['netProfit'], $r['grossMargin'],
            ]);
        }

        return implode("\n", $lines);
    }
}
