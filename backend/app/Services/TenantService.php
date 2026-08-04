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

        $query = Tenant::query()->orderByDesc('id');

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
        $tenant->update([
            'package' => $data['package'] ?? $tenant->package,
            'concurrent' => $data['concurrent'] ?? $tenant->concurrent,
            'ai_quota' => $data['aiQuota'] ?? $data['ai_quota'] ?? $tenant->ai_quota,
            'binds' => $data['binds'] ?? $tenant->binds,
            'kb' => $data['kb'] ?? $tenant->kb,
        ]);
        $this->bustCache($tenant->id);
        return $tenant->fresh();
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
            'ent' => ['concurrent' => 50, 'ai_quota' => 50000, 'binds' => 50, 'kb' => 50],
            'pro' => ['concurrent' => 20, 'ai_quota' => 10000, 'binds' => 20, 'kb' => 10],
            default => ['concurrent' => 5, 'ai_quota' => 1000, 'binds' => 5, 'kb' => 1],
        };
    }

    private function bustCache(int $tenantId): void
    {
        Cache::store('redis')->forget(self::CONFIG_CACHE_PREFIX.$tenantId);
        Cache::store('redis')->forget('tenant:stats');
        Cache::store('redis')->forget('dashboard:stats:v1');
    }
}
