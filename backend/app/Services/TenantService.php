<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 租户管理服务
 */
class TenantService
{
    private const CONFIG_CACHE_PREFIX = 'tenant:config:';

    public function list(array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 10)));

        $query = Tenant::query()->with('currentAiParamTemplate')->orderByDesc('id');

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('contact', 'like', "%{$kw}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('status', (int) $filters['status']);
        }

        if (!empty($filters['package'])) {
            $query->where('package', $filters['package']);
        }

        return $query->paginate($size, ['*'], 'page', $page);
    }

    public function stats(): array
    {
        return Cache::store('redis')->remember('tenant:stats', 60, function () {
            return [
                'total' => Tenant::count(),
                'enabled' => Tenant::where('status', 1)->count(),
                'todayNew' => Tenant::whereDate('created_at', today())->count(),
                'monthActive' => Tenant::where('status', 1)
                    ->where('updated_at', '>=', now()->startOfMonth())
                    ->count(),
            ];
        });
    }

    public function create(array $data): Tenant
    {
        $defaults = $this->packageDefaults($data['package'] ?? 'basic');
        $tenant = Tenant::create(array_merge($defaults, $data));
        $this->bustCache($tenant->id);
        return $tenant;
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);
        $this->bustCache($tenant->id);
        return $tenant->fresh();
    }

    public function updatePackage(Tenant $tenant, array $data): Tenant
    {
        $newPackage = $data['package'] ?? $tenant->package;
        $packageChanged = $newPackage !== $tenant->package;

        $payload = [
            'package' => $newPackage,
            'concurrent' => $data['concurrent'] ?? $tenant->concurrent,
            'ai_quota' => $data['aiQuota'] ?? $data['ai_quota'] ?? $tenant->ai_quota,
            'binds' => $data['binds'] ?? $tenant->binds,
            'kb' => $data['kb'] ?? $tenant->kb,
        ];

        // 套餐等级变更：先按 saas_package_setting 同步 IP 配额
        if ($packageChanged) {
            $setting = \App\Models\PackageSetting::findByPackageCode((string) $newPackage);
            if ($setting) {
                $payload['max_proxy_ip'] = $setting->max_proxy_ip;
                $payload['daily_proxy_request_limit'] = $setting->daily_proxy_request_limit;
                $payload['allow_self_proxy'] = (int) $setting->allow_self_proxy;
            } else {
                $defaults = $this->packageDefaults((string) $newPackage);
                $payload['max_proxy_ip'] = $defaults['max_proxy_ip'];
                $payload['daily_proxy_request_limit'] = $defaults['daily_proxy_request_limit'];
                $payload['allow_self_proxy'] = $defaults['allow_self_proxy'];
            }
        }

        // 请求显式传入时可覆盖（租户级微调）
        if (array_key_exists('maxProxyIp', $data) || array_key_exists('max_proxy_ip', $data)) {
            $payload['max_proxy_ip'] = $this->nullableInt($data['maxProxyIp'] ?? $data['max_proxy_ip'] ?? null);
        }
        if (array_key_exists('dailyProxyRequestLimit', $data) || array_key_exists('daily_proxy_request_limit', $data)) {
            $payload['daily_proxy_request_limit'] = $this->nullableInt(
                $data['dailyProxyRequestLimit'] ?? $data['daily_proxy_request_limit'] ?? null
            );
        }
        if (array_key_exists('allowSelfProxy', $data) || array_key_exists('allow_self_proxy', $data)) {
            $v = $data['allowSelfProxy'] ?? $data['allow_self_proxy'];
            $payload['allow_self_proxy'] = !empty($v) ? 1 : 0;
        }

        // 若租户 IP 字段仍为空，按当前套餐补齐
        if (!array_key_exists('max_proxy_ip', $payload) && $tenant->max_proxy_ip === null
            && $tenant->daily_proxy_request_limit === null && $tenant->allow_self_proxy === null) {
            $setting = \App\Models\PackageSetting::findByPackageCode((string) $newPackage);
            if ($setting) {
                $payload['max_proxy_ip'] = $setting->max_proxy_ip;
                $payload['daily_proxy_request_limit'] = $setting->daily_proxy_request_limit;
                $payload['allow_self_proxy'] = (int) $setting->allow_self_proxy;
            }
        }

        $tenant->update($payload);
        $this->bustCache($tenant->id);

        $fresh = $tenant->fresh();
        // 升级解锁 / 降级自动切换可用 AI 模板
        \App\Support\PackageQuota::reconcileTenantAiTemplate($fresh);
        // 降级后运行中爬虫超出上限则自动暂停多余任务
        if ($packageChanged) {
            \App\Support\PackageQuota::reconcileCrawlerTasksOnDowngrade($fresh);
        }

        return $tenant->fresh('currentAiParamTemplate');
    }

    public function toggleStatus(Tenant $tenant, int $status): Tenant
    {
        $tenant->update(['status' => $status]);
        $this->bustCache($tenant->id);
        return $tenant;
    }

    public function delete(Tenant $tenant): void
    {
        $id = $tenant->id;
        $tenant->delete();
        $this->bustCache($id);
    }

    /** 获取租户配置（Redis 缓存） */
    public function getCachedConfig(int $tenantId): array
    {
        return Cache::store('redis')->remember(self::CONFIG_CACHE_PREFIX.$tenantId, 300, function () use ($tenantId) {
            $t = Tenant::findOrFail($tenantId);
            return [
                'id' => $t->id,
                'name' => $t->name,
                'package' => $t->package,
                'concurrent' => $t->concurrent,
                'aiQuota' => $t->ai_quota,
                'binds' => $t->binds,
                'kb' => $t->kb,
                'maxProxyIp' => $t->max_proxy_ip,
                'dailyProxyRequestLimit' => $t->daily_proxy_request_limit,
                'allowSelfProxy' => $t->allow_self_proxy === null ? null : ((int) $t->allow_self_proxy === 1),
                'status' => $t->status,
            ];
        });
    }

    public function exportCsv(array $filters): StreamedResponse
    {
        $filters['size'] = 10000;
        $filters['page'] = 1;
        $rows = $this->list($filters);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', '租户名称', '联系人', '电话', '邮箱', '套餐', '状态', '创建时间']);
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->id, $t->name, $t->contact, $t->phone, $t->email,
                    $t->package, $t->status ? '启用' : '禁用',
                    optional($t->created_at)->format('Y-m-d'),
                ]);
            }
            fclose($out);
        }, 'tenants_'.date('YmdHis').'.csv');
    }

    private function packageDefaults(string $package): array
    {
        return match ($package) {
            'ent' => [
                'concurrent' => 50,
                'ai_quota' => 50000,
                'binds' => 50,
                'kb' => 50,
                'max_proxy_ip' => null,
                'daily_proxy_request_limit' => null,
                'allow_self_proxy' => 1,
            ],
            'pro' => [
                'concurrent' => 20,
                'ai_quota' => 10000,
                'binds' => 20,
                'kb' => 10,
                'max_proxy_ip' => 15,
                'daily_proxy_request_limit' => 5000,
                'allow_self_proxy' => 1,
            ],
            default => [
                'concurrent' => 5,
                'ai_quota' => 1000,
                'binds' => 5,
                'kb' => 1,
                'max_proxy_ip' => 3,
                'daily_proxy_request_limit' => 500,
                'allow_self_proxy' => 0,
            ],
        };
    }

    /** -1 / 空 → null（无上限） */
    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '' || $v === -1 || $v === '-1') {
            return null;
        }

        return max(0, (int) $v);
    }

    private function bustCache(int $tenantId): void
    {
        Cache::store('redis')->forget(self::CONFIG_CACHE_PREFIX.$tenantId);
        Cache::store('redis')->forget('tenant:stats');
        Cache::store('redis')->forget('dashboard:stats:v1');
        // v2 按角色缓存，变更租户后短暂旧数据可接受（TTL 45s）
    }
}
