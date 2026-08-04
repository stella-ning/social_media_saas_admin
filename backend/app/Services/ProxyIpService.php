<?php

namespace App\Services;

use App\Models\ProxyIp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProxyIpService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 10)));

        $query = ProxyIp::query()->with('tenant')->orderByDesc('id');

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('address', 'like', "%{$kw}%")->orWhere('location', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($size, ['*'], 'page', $page);
    }

    /** 批量导入：每行 IP:端口[:用户名:密码] */
    public function import(string $listText, string $location = '未知'): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $listText))));
        $created = [];
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            $address = count($parts) >= 2 ? $parts[0].':'.$parts[1] : $line;
            $created[] = ProxyIp::create([
                'address' => $address,
                'location' => $location ?: '未知',
                'protocol' => 'HTTP/HTTPS',
                'status' => 'running',
                'load' => 0,
                'capacity' => 100,
                'latency_ms' => random_int(20, 100),
            ]);
        }
        return $created;
    }

    public function check(ProxyIp $ip): ProxyIp
    {
        // 模拟连通性检测
        $ok = $ip->status !== 'error' || random_int(0, 1) === 1;
        $ip->update([
            'status' => $ok ? 'running' : 'error',
            'latency_ms' => $ok ? random_int(20, 100) : null,
            'load' => $ok ? ($ip->load ?: random_int(0, 20)) : 0,
        ]);
        return $ip->fresh();
    }

    public function bindTenant(ProxyIp $ip, ?int $tenantId): ProxyIp
    {
        $ip->update(['tenant_id' => $tenantId]);
        return $ip->fresh('tenant');
    }

    public function delete(ProxyIp $ip): void
    {
        $ip->delete();
    }
}
