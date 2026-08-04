<?php

namespace App\Services;

use App\Models\SocialAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 社媒账号服务
 */
class SocialAccountService
{
    public function list(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 10)));

        $query = SocialAccount::query()->with('tenant')->orderByDesc('id');

        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        } elseif (!empty($filters['tenantId'])) {
            $query->where('tenant_id', $filters['tenantId']);
        } elseif (!empty($filters['tenant'])) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', $filters['tenant']));
        }

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")->orWhere('uid', 'like', "%{$kw}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        return $query->paginate($size, ['*'], 'page', $page);
    }

    public function create(array $data): SocialAccount
    {
        $ip = $data['bindIp'] ?? $data['ip'] ?? 'auto';

        return SocialAccount::create([
            'name' => $data['name'] ?? ('新绑定账号_'.($data['platform'] ?? '抖音')),
            'uid' => $data['uid'] ?? (string) random_int(1000000, 9999999),
            'avatar' => $data['avatar'] ?? 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
            'platform' => $data['platform'],
            'bind_ip' => $ip === 'auto' ? '123.56.xx.102' : $ip,
            'tenant_id' => $data['tenantId'] ?? $data['tenant_id'],
            'status' => 'online',
            'cookie' => $data['token'] ?? $data['cookie'] ?? null,
        ])->load('tenant');
    }

    public function unbind(SocialAccount $account): void
    {
        $account->delete();
    }

    public function refreshStatus(?int $scopeTenantId = null): array
    {
        $query = SocialAccount::query()->with('tenant');
        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        }
        $list = $query->get();
        foreach ($list as $item) {
            $item->update(['status' => random_int(0, 100) > 30 ? 'online' : 'offline']);
        }

        return $list->map->toFrontendArray()->values()->all();
    }
}
