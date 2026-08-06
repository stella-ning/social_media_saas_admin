<?php

namespace App\Services;

use App\Models\CrawlerTask;
use App\Models\CrawlerTaskLog;
use App\Models\CrmLead;
use App\Models\Message;
use App\Models\MessageSession;
use App\Models\ProxyIp;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * 仪表盘：按角色 / 租户真实聚合，可见即可得
 */
class DashboardService
{
    private const CACHE_TTL = 45; // 秒

    public function overview(?User $user = null): array
    {
        $user = $user ?: auth()->user();
        $role = $user?->role ?: 'operator';
        $tenantId = $user && !$user->isSuperAdmin() ? (int) $user->tenant_id : null;
        $cacheKey = sprintf(
            'dashboard:stats:v2:%s:%s:%s',
            $role,
            $tenantId ?: 'all',
            $user?->id ?: 0
        );

        try {
            return Cache::store('redis')->remember($cacheKey, self::CACHE_TTL, function () use ($user, $role, $tenantId) {
                return $this->buildOverview($user, $role, $tenantId);
            });
        } catch (\Throwable) {
            return $this->buildOverview($user, $role, $tenantId);
        }
    }

    public function trendChart(?User $user = null): array
    {
        $user = $user ?: auth()->user();
        $tenantId = $user && !$user->isSuperAdmin() ? (int) $user->tenant_id : null;

        return $this->buildTrend($user, $tenantId);
    }

    public function intentPie(?User $user = null): array
    {
        $user = $user ?: auth()->user();
        $tenantId = $user && !$user->isSuperAdmin() ? (int) $user->tenant_id : null;

        return $this->buildIntentPie($user, $tenantId);
    }

    public function clearCache(?User $user = null): void
    {
        // 精确清当前用户；超管变更租户时由 TenantService 另清旧 key
        if ($user) {
            $tenantId = $user->isSuperAdmin() ? 'all' : (string) ($user->tenant_id ?: 0);
            $key = sprintf('dashboard:stats:v2:%s:%s:%s', $user->role, $tenantId, $user->id);
            try {
                Cache::store('redis')->forget($key);
            } catch (\Throwable) {
                // ignore
            }
        }
        try {
            Cache::store('redis')->forget('dashboard:stats:v1');
        } catch (\Throwable) {
            // ignore
        }
    }

    private function buildOverview(?User $user, string $role, ?int $tenantId): array
    {
        return [
            'scope' => [
                'role' => $role,
                'tenantId' => $tenantId,
                'label' => $this->scopeLabel($user, $role),
            ],
            'statCards' => $this->buildStatCards($user, $role, $tenantId),
            'trendChart' => $this->buildTrend($user, $tenantId),
            'intentPie' => $this->buildIntentPie($user, $tenantId),
            'platformBreakdown' => $this->buildPlatformBreakdown($tenantId, $role, $user),
            'version' => 'v1.0.0 Stable',
        ];
    }

    private function scopeLabel(?User $user, string $role): string
    {
        return match ($role) {
            'super_admin' => '全平台汇总',
            'tenant_admin' => '租户：'.($user?->tenant?->name ?: '本租户'),
            'operator' => '个人跟进数据',
            default => '当前账号',
        };
    }

    private function buildStatCards(?User $user, string $role, ?int $tenantId): array
    {
        if ($role === 'operator') {
            return $this->operatorCards($user, $tenantId);
        }
        if ($role === 'tenant_admin') {
            return $this->tenantCards($tenantId);
        }

        return $this->platformCards();
    }

    /** 超管：全平台真实汇总 */
    private function platformCards(): array
    {
        $runningTasks = CrawlerTask::query()->where('status', 'running')->count();
        $runningYesterday = CrawlerTask::query()
            ->where('status', 'running')
            ->where('updated_at', '<', now()->startOfDay())
            ->count();

        $leadCount = CrmLead::query()->count();
        $leadYesterday = CrmLead::query()->where('created_at', '<', now()->startOfDay())->count();

        $highIntent = CrmLead::query()->where('intent', 'high')->count();
        $highYesterday = CrmLead::query()
            ->where('intent', 'high')
            ->where('created_at', '<', now()->startOfDay())
            ->count();

        $activeTenants = Tenant::query()->where('status', 1)->count();
        $publicPool = ProxyIp::query()
            ->where(function (Builder $q) {
                $q->whereNull('pool_type')->orWhere('pool_type', 'public');
            })
            ->count();

        return [
            $this->card('运行中任务', $runningTasks, $runningYesterday, '较昨日'),
            $this->card('累计采集线索', $leadCount, $leadYesterday, '较昨日新增前'),
            $this->card('高意向客户', $highIntent, $highYesterday, '较昨日新增前'),
            $this->card('活跃租户', $activeTenants, null, '公共池 IP '.$publicPool),
        ];
    }

    /** 租户管理员：本租户 */
    private function tenantCards(?int $tenantId): array
    {
        $tasksQ = CrawlerTask::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $running = (clone $tasksQ)->where('status', 'running')->count();
        $todayCollect = (int) (clone $tasksQ)->sum('today_count');

        $accounts = SocialAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('account_status', 1)
            ->count();

        $leads = CrmLead::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->count();
        $leadsYesterday = CrmLead::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '<', now()->startOfDay())
            ->count();

        $unread = MessageSession::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('unread', '>', 0)
            ->count();

        return [
            $this->card('运行中任务', $running, null, '今日采集 '.$todayCollect),
            $this->card('在线社媒账号', $accounts, null, '状态正常'),
            $this->card('累计线索', $leads, $leadsYesterday, '较昨日新增前'),
            $this->card('未读会话', $unread, null, '待处理消息'),
        ];
    }

    /** 业务员：个人跟进 */
    private function operatorCards(?User $user, ?int $tenantId): array
    {
        $leadsQ = $this->operatorLeadQuery($user, $tenantId);
        $myLeads = (clone $leadsQ)->count();
        $high = (clone $leadsQ)->where('intent', 'high')->count();

        $sessionsQ = MessageSession::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $unread = (clone $sessionsQ)->where('unread', '>', 0)->count();
        $pending = (clone $sessionsQ)
            ->where(function (Builder $q) {
                $q->where('handle_status', MessageSession::HANDLE_UNREAD)
                    ->orWhere('session_status', 'open');
            })
            ->count();

        return [
            $this->card('我的跟进线索', $myLeads, null, '当前账号'),
            $this->card('高意向客户', $high, null, '优先跟进'),
            $this->card('未读会话', $unread, null, '本租户会话'),
            $this->card('待处理会话', $pending, null, '含未读/进行中'),
        ];
    }

    private function operatorLeadQuery(?User $user, ?int $tenantId): Builder
    {
        $q = CrmLead::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        if (!$user) {
            return $q->whereRaw('1=0');
        }
        $name = (string) $user->display_name;
        $username = (string) $user->username;

        return $q->where(function (Builder $inner) use ($name, $username) {
            if ($name !== '') {
                $inner->orWhere('follower', 'like', '%'.$name.'%');
            }
            if ($username !== '') {
                $inner->orWhere('follower', 'like', '%'.$username.'%');
            }
            if ($name === '' && $username === '') {
                $inner->whereRaw('1=0');
            }
        });
    }

    private function card(string $title, int $value, ?int $baseline, string $compare): array
    {
        $trendUp = true;
        $percent = '—';
        if ($baseline !== null) {
            $delta = $value - $baseline;
            if ($baseline > 0) {
                $percent = round(abs($delta) / $baseline * 100, 1).'%';
            } elseif ($delta > 0) {
                $percent = '新增';
            } else {
                $percent = '持平';
            }
            $trendUp = $delta >= 0;
            if (!str_contains($compare, '较')) {
                $compare = '较昨日';
            }
        }

        return [
            'title' => $title,
            'value' => number_format($value),
            'rawValue' => $value,
            'percent' => $percent,
            'compare' => $compare,
            'trendUp' => $trendUp,
        ];
    }

    /**
     * 近 7 日趋势：采集日志 / 新增线索 / 会话消息
     */
    private function buildTrend(?User $user, ?int $tenantId): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $days[] = now()->subDays($i)->startOfDay();
        }
        $xAxis = array_map(fn (Carbon $d) => $d->format('m-d'), $days);

        $collect = [];
        $leads = [];
        $messages = [];

        foreach ($days as $day) {
            $start = $day->copy();
            $end = $day->copy()->endOfDay();

            $taskIds = CrawlerTask::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->pluck('id');

            $collect[] = CrawlerTaskLog::query()
                ->whereIn('task_id', $taskIds->isEmpty() ? [0] : $taskIds)
                ->whereBetween('logged_at', [$start, $end])
                ->count();

            $leadQ = CrmLead::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->whereBetween('created_at', [$start, $end]);
            if ($user?->isOperator()) {
                $leadQ = $this->operatorLeadQuery($user, $tenantId)
                    ->whereBetween('created_at', [$start, $end]);
            }
            $leads[] = $leadQ->count();

            if (Schema::hasTable((new Message)->getTable())) {
                $sessionIds = MessageSession::query()
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->pluck('id');
                $messages[] = Message::query()
                    ->whereIn('session_id', $sessionIds->isEmpty() ? [0] : $sessionIds)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();
            } else {
                $messages[] = 0;
            }
        }

        return [
            'xAxis' => $xAxis,
            'series' => [
                [
                    'name' => '任务日志',
                    'color' => '#409eff',
                    'data' => $collect,
                ],
                [
                    'name' => '新增线索',
                    'color' => '#67c23a',
                    'data' => $leads,
                ],
                [
                    'name' => '会话消息',
                    'color' => '#e6a23c',
                    'data' => $messages,
                ],
            ],
        ];
    }

    private function buildIntentPie(?User $user, ?int $tenantId): array
    {
        $base = CrmLead::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        if ($user?->isOperator()) {
            $base = $this->operatorLeadQuery($user, $tenantId);
        }

        $map = [
            'high' => ['name' => '高意向', 'color' => '#f56c6c'],
            'mid' => ['name' => '中意向', 'color' => '#e6a23c'],
            'low' => ['name' => '低意向', 'color' => '#409eff'],
            'none' => ['name' => '无意向', 'color' => '#909399'],
        ];

        $counts = (clone $base)
            ->selectRaw('intent, count(*) as c')
            ->groupBy('intent')
            ->pluck('c', 'intent')
            ->toArray();

        $result = [];
        foreach ($map as $key => $meta) {
            $result[] = [
                'name' => $meta['name'],
                'value' => (int) ($counts[$key] ?? 0),
                'color' => $meta['color'],
            ];
        }

        // 未知 intent 归入「无意向」
        $known = array_sum(array_map(fn ($r) => $r['value'], $result));
        $total = (clone $base)->count();
        if ($total > $known) {
            $result[3]['value'] += ($total - $known);
        }

        return $result;
    }

    /** 社媒平台分布（任务数），中文标签 */
    private function buildPlatformBreakdown(?int $tenantId, string $role, ?User $user): array
    {
        if ($role === 'operator') {
            return [];
        }

        $rows = CrawlerTask::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('platform, count(*) as c')
            ->groupBy('platform')
            ->pluck('c', 'platform')
            ->toArray();

        $out = [];
        foreach ($rows as $platform => $count) {
            $out[] = [
                'platform' => (string) $platform,
                'label' => \App\Support\PlatformEnum::labelFromAny($platform),
                'value' => (int) $count,
            ];
        }

        return $out;
    }
}
