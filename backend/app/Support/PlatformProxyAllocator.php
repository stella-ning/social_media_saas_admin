<?php

namespace App\Support;

use App\Models\CrawlerTask;
use App\Models\PackageSetting;
use App\Models\ProxyIp;
use App\Models\ProxyIpAccessLog;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\TenantProxy;
use RuntimeException;

/**
 * 平台公共住宅代理分配器
 * - 租户禁止自有 IP；爬虫启动时从对应等级公共池自动分配
 * - 企业版可启用专属隔离池（pool_type=dedicated）
 */
class PlatformProxyAllocator
{
    /**
     * 为社媒账号分配平台公共代理（若尚未绑定）
     */
    public static function ensureAccountProxy(SocialAccount $account): ProxyIp
    {
        $account->loadMissing('proxy');
        if ($account->bind_proxy_id && $account->proxy) {
            return $account->proxy;
        }

        $tenant = Tenant::query()->findOrFail((int) $account->tenant_id);
        PackageQuota::assertPackageActive($tenant);

        $setting = PackageQuota::settingForTenant($tenant);
        $platformKey = PlatformEnum::toPythonKey((int) $account->platform);
        $allowed = $setting->allow_platforms ?: [];
        if ($allowed && !in_array($platformKey, $allowed, true)) {
            throw new RuntimeException('当前套餐不支持该平台，无法分配代理 IP');
        }

        PackageQuota::assertCanBindProxyIp((int) $tenant->id);

        $proxy = self::pickFromPool($tenant, $platformKey);
        $account->update(['bind_proxy_id' => $proxy->id]);

        TenantProxy::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'proxy_ip_id' => $proxy->id,
        ]);

        // 展示归属：分配给租户但不代表自有上传
        if (!$proxy->tenant_id) {
            $proxy->update(['tenant_id' => $tenant->id]);
        }

        ProxyIpAccessLog::create([
            'proxy_ip_id' => $proxy->id,
            'tenant_id' => $tenant->id,
            'crawler_task_id' => null,
            'action' => 'assign',
            'result' => 'ok',
            'detail' => '社媒账号自动分配平台公共代理 account='.$account->id,
            'logged_at' => now(),
        ]);

        return $proxy->fresh();
    }

    /**
     * 爬虫任务启动：确保执行账号已有平台代理
     */
    public static function ensureTaskProxy(CrawlerTask $task): ProxyIp
    {
        $task->loadMissing('socialAccount.proxy');
        if (!$task->social_account_id || !$task->socialAccount) {
            throw new RuntimeException('爬虫任务未绑定执行账号，无法分配平台代理');
        }

        $proxy = self::ensureAccountProxy($task->socialAccount);

        ProxyIpAccessLog::create([
            'proxy_ip_id' => $proxy->id,
            'tenant_id' => $task->tenant_id,
            'crawler_task_id' => $task->id,
            'action' => 'request',
            'result' => 'ok',
            'detail' => '爬虫任务启动使用平台公共代理',
            'logged_at' => now(),
        ]);

        return $proxy;
    }

    /**
     * 从平台池挑选可用 IP
     */
    public static function pickFromPool(Tenant $tenant, ?string $platformKey = null): ProxyIp
    {
        $useDedicated = (int) ($tenant->dedicated_ip_pool_enabled ?? 0) === 1
            && (int) PackageQuota::settingForTenant($tenant)->enable_dedicated_ip_pool === 1;

        $query = ProxyIp::query()
            ->whereIn('status', ['running', 'idle'])
            ->orderBy('load')
            ->orderBy('id');

        if ($useDedicated) {
            $query->where('pool_type', 'dedicated')
                ->where(function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id)->orWhereNull('tenant_id');
                });
        } else {
            $query->where('pool_type', 'public')
                ->where(function ($q) {
                    // 未被占用或仅作分配展示
                    $q->whereNull('tenant_id')
                        ->orWhereDoesntHave('socialAccounts');
                });
        }

        if ($platformKey) {
            $query->where(function ($q) use ($platformKey) {
                $q->whereNull('platform_scope')
                    ->orWhereJsonContains('platform_scope', $platformKey);
            });
        }

        // 避免已被其他账号占用的一号一 IP
        $usedIds = SocialAccount::query()
            ->whereNotNull('bind_proxy_id')
            ->pluck('bind_proxy_id')
            ->all();
        if ($usedIds) {
            $query->whereNotIn('id', $usedIds);
        }

        $proxy = $query->first();
        if (!$proxy) {
            // 回退：任意空闲公共池
            $proxy = ProxyIp::query()
                ->where('pool_type', $useDedicated ? 'dedicated' : 'public')
                ->whereIn('status', ['running', 'idle'])
                ->whereNotIn('id', $usedIds ?: [0])
                ->orderBy('load')
                ->first();
        }

        if (!$proxy) {
            throw new RuntimeException('平台公共代理 IP 池暂无可用资源，请联系平台运维扩容');
        }

        return $proxy;
    }

    public static function rotateIfEnabled(Tenant $tenant, SocialAccount $account): ?ProxyIp
    {
        $setting = PackageQuota::settingForTenant($tenant);
        if ((int) $setting->enable_ip_rotate !== 1 || !(int) ($tenant->ip_rotate_enabled ?? 0)) {
            return null;
        }

        $oldId = $account->bind_proxy_id;
        $account->update(['bind_proxy_id' => null]);
        $proxy = self::ensureAccountProxy($account->fresh());

        ProxyIpAccessLog::create([
            'proxy_ip_id' => $proxy->id,
            'tenant_id' => $tenant->id,
            'action' => 'rotate',
            'result' => 'ok',
            'detail' => 'IP 自动轮换 old='.$oldId.' new='.$proxy->id,
            'logged_at' => now(),
        ]);

        return $proxy;
    }
}
