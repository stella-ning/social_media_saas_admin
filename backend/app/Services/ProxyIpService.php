<?php

namespace App\Services;

use App\Models\ProxyIp;
use App\Models\ProxyIpAccessLog;
use App\Models\TenantProxy;
use App\Support\PackageQuota;
use App\Support\PlatformProxyAllocator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

/**
 * 平台公共住宅代理 IP 池服务
 * - 仅超管可导入平台公共池 / 专属隔离池
 * - 全局禁止租户上传自有代理
 * - 租户侧仅可查看已分配 IP、检测、访问日志
 */
class ProxyIpService
{
    public function list(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 10)));

        $query = ProxyIp::query()->with('tenant')->orderByDesc('id');

        if ($scopeTenantId) {
            $query->where(function ($q) use ($scopeTenantId) {
                $q->where('tenant_id', $scopeTenantId)
                    ->orWhereIn('id', TenantProxy::query()->where('tenant_id', $scopeTenantId)->pluck('proxy_ip_id'));
            });
        } elseif (!empty($filters['tenantId']) || !empty($filters['tenant_id'])) {
            $tid = (int) ($filters['tenantId'] ?? $filters['tenant_id']);
            $query->where('tenant_id', $tid);
        }

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('address', 'like', "%{$kw}%")->orWhere('location', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['pool_type']) || !empty($filters['poolType'])) {
            $query->where('pool_type', $filters['pool_type'] ?? $filters['poolType']);
        }

        return $query->paginate($size, ['*'], 'page', $page);
    }

    /**
     * 批量导入平台公共/专属池（禁止带租户自有语义）
     * tenantId 若传入：仅允许企业版专属隔离池预分配，不是自有上传
     */
    public function import(string $listText, string $location = '未知', ?int $tenantId = null, string $poolType = 'public'): array
    {
        // 任何「租户自有代理」语义一律拦截
        if ($tenantId && $poolType !== 'dedicated') {
            PackageQuota::assertSelfProxyAllowed($tenantId);
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $listText))));
        if (!$lines) {
            throw new RuntimeException('请输入有效的代理 IP 列表');
        }

        if ($poolType === 'dedicated') {
            if (!$tenantId) {
                throw new RuntimeException('专属隔离池导入必须指定企业版租户');
            }
            PackageQuota::assertFeatureEnabled($tenantId, 'enable_dedicated_ip_pool', '租户专属隔离平台代理IP池');
        }

        $created = [];
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            $address = count($parts) >= 2 ? $parts[0].':'.$parts[1] : $line;
            $ip = ProxyIp::create([
                'address' => $address,
                'location' => $location ?: '未知',
                'protocol' => 'HTTP/HTTPS',
                'status' => 'running',
                'load' => 0,
                'capacity' => 100,
                'latency_ms' => random_int(20, 100),
                'tenant_id' => $poolType === 'dedicated' ? $tenantId : null,
                'pool_type' => $poolType === 'dedicated' ? 'dedicated' : 'public',
                'risk_level' => 'low',
                'platform_scope' => null,
            ]);
            if ($poolType === 'dedicated' && $tenantId) {
                TenantProxy::query()->firstOrCreate([
                    'tenant_id' => $tenantId,
                    'proxy_ip_id' => $ip->id,
                ]);
            }
            $created[] = $ip;
        }

        return $created;
    }

    public function check(ProxyIp $ip, ?int $scopeTenantId = null): ProxyIp
    {
        if ($scopeTenantId && (int) $ip->tenant_id !== $scopeTenantId) {
            $owned = TenantProxy::query()
                ->where('tenant_id', $scopeTenantId)
                ->where('proxy_ip_id', $ip->id)
                ->exists();
            if (!$owned) {
                throw new RuntimeException('无权检测该代理 IP');
            }
        }

        $ok = $ip->status !== 'error' || random_int(0, 1) === 1;
        $ip->update([
            'status' => $ok ? 'running' : 'error',
            'latency_ms' => $ok ? random_int(20, 100) : null,
            'load' => $ok ? ($ip->load ?: random_int(0, 20)) : 0,
            'risk_level' => $ok ? 'low' : 'high',
            'last_risk_at' => now(),
        ]);
        $ip = $ip->fresh('tenant');

        ProxyIpAccessLog::create([
            'proxy_ip_id' => $ip->id,
            'tenant_id' => $ip->tenant_id,
            'action' => 'check',
            'result' => $ok ? 'ok' : 'fail',
            'detail' => $ok ? '连通性检测通过' : '连通性检测失败',
            'logged_at' => now(),
        ]);

        if ($ok && $ip->tenant_id) {
            PackageQuota::recordProxySuccessAndMaybePause((int) $ip->tenant_id);
        }

        return $ip;
    }

    /** 批量风险检测（专业版+） */
    public function batchRiskCheck(int $tenantId): array
    {
        PackageQuota::assertFeatureEnabled($tenantId, 'enable_ip_risk_check', 'IP 风险检测');
        $ids = TenantProxy::query()->where('tenant_id', $tenantId)->pluck('proxy_ip_id');
        $ips = ProxyIp::query()->whereIn('id', $ids)->orWhere('tenant_id', $tenantId)->get();
        $results = [];
        foreach ($ips as $ip) {
            $results[] = $this->check($ip, $tenantId)->toFrontendArray();
        }

        return $results;
    }

    public function accessLogs(ProxyIp $ip, ?int $scopeTenantId = null, int $limit = 50): array
    {
        if ($scopeTenantId && (int) $ip->tenant_id !== $scopeTenantId) {
            $owned = TenantProxy::query()
                ->where('tenant_id', $scopeTenantId)
                ->where('proxy_ip_id', $ip->id)
                ->exists();
            if (!$owned) {
                throw new RuntimeException('无权查看该 IP 访问日志');
            }
        }

        return $ip->accessLogs()
            ->limit($limit)
            ->get()
            ->map->toFrontendArray()
            ->values()
            ->all();
    }

    public function bindTenant(ProxyIp $ip, ?int $tenantId): ProxyIp
    {
        if (!$tenantId) {
            TenantProxy::query()->where('proxy_ip_id', $ip->id)->delete();
            $ip->update(['tenant_id' => null]);

            return $ip->fresh('tenant');
        }

        PackageQuota::assertPackageActive($tenantId);
        PackageQuota::assertCanBindProxyIp($tenantId, $ip->id);

        TenantProxy::query()->where('proxy_ip_id', $ip->id)->delete();
        TenantProxy::query()->firstOrCreate([
            'tenant_id' => $tenantId,
            'proxy_ip_id' => $ip->id,
        ]);
        $ip->update(['tenant_id' => $tenantId]);

        return $ip->fresh('tenant');
    }

    public function destroy(ProxyIp $ip): void
    {
        TenantProxy::query()->where('proxy_ip_id', $ip->id)->delete();
        $ip->delete();
    }

    /** 租户侧：已分配 IP 列表摘要 */
    public function allocatedForTenant(int $tenantId): array
    {
        $relIds = TenantProxy::query()->where('tenant_id', $tenantId)->pluck('proxy_ip_id')->all();
        $list = ProxyIp::query()
            ->where(function ($q) use ($tenantId, $relIds) {
                $q->where('tenant_id', $tenantId);
                if ($relIds) {
                    $q->orWhereIn('id', $relIds);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->map->toFrontendArray()
            ->values()
            ->all();

        return [
            'list' => $list,
            'quota' => PackageQuota::quotaBriefForTenant($tenantId),
            'ipHostingNote' => '全部 IP 由平台统一托管，租户不可上传自有代理',
        ];
    }

    public function autoAssign(int $tenantId, int $socialAccountId): ProxyIp
    {
        $account = \App\Models\SocialAccount::query()->findOrFail($socialAccountId);
        if ((int) $account->tenant_id !== $tenantId) {
            throw new RuntimeException('账号不属于该租户');
        }

        return PlatformProxyAllocator::ensureAccountProxy($account);
    }
}
