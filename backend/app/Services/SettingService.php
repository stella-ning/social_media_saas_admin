<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SettingService
{
    public function getBasic(): array
    {
        return [
            'name' => $this->get('basic', 'system_name', '社媒AI自动化SaaS平台'),
            'copyright' => $this->get('basic', 'copyright', '© 2026 SocialAI Technology Co., Ltd. 粤ICP备12345678号'),
            'notify' => (bool) $this->get('basic', 'notify', true),
            'version' => 'v1.0.0 Stable',
        ];
    }

    public function saveBasic(array $data): array
    {
        $this->set('basic', 'system_name', $data['name'] ?? null);
        $this->set('basic', 'copyright', $data['copyright'] ?? null);
        $this->set('basic', 'notify', $data['notify'] ?? true);
        return $this->getBasic();
    }

    public function getSecurity(): array
    {
        return [
            'lockOnFail' => (bool) $this->get('security', 'lock_on_fail', true),
            'pwdDays' => (int) $this->get('security', 'pwd_days', 90),
            'twoFactor' => (bool) $this->get('security', 'two_factor', false),
            'sessionMin' => (int) $this->get('security', 'session_min', 60),
            'ipWhitelist' => (string) $this->get('security', 'ip_whitelist', ''),
        ];
    }

    public function saveSecurity(array $data): array
    {
        $map = [
            'lockOnFail' => 'lock_on_fail',
            'pwdDays' => 'pwd_days',
            'twoFactor' => 'two_factor',
            'sessionMin' => 'session_min',
            'ipWhitelist' => 'ip_whitelist',
        ];
        foreach ($map as $camel => $key) {
            if (array_key_exists($camel, $data)) {
                $this->set('security', $key, $data[$camel]);
            }
        }
        return $this->getSecurity();
    }

    public function users(): array
    {
        return User::query()->with('tenant')->orderBy('id')->get()->map(function (User $u) {
            return [
                'id' => $u->id,
                'username' => $u->username,
                'role' => $u->role,
                'tenant' => $u->tenant?->name ?? '',
                'lastLogin' => optional($u->last_login_at)?->format('Y-m-d H:i') ?? '-',
                'status' => $u->status,
            ];
        })->values()->all();
    }

    public function createUser(array $data): User
    {
        return User::create([
            'username' => $data['username'],
            'display_name' => $data['displayName'] ?? $data['username'],
            'password' => $data['password'],
            'role' => $data['role'],
            'tenant_id' => $data['tenantId'] ?? $data['tenant_id'] ?? null,
            'status' => 1,
        ]);
    }

    public function updateUser(User $user, array $data): User
    {
        $payload = [];
        if (isset($data['role'])) {
            $payload['role'] = $data['role'];
        }
        if (array_key_exists('tenantId', $data) || array_key_exists('tenant_id', $data)) {
            $payload['tenant_id'] = $data['tenantId'] ?? $data['tenant_id'];
        }
        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }
        $user->update($payload);
        return $user->fresh('tenant');
    }

    public function toggleUser(User $user): User
    {
        if ($user->username === 'admin') {
            abort(403, '不能禁用超级管理员 admin');
        }
        $user->update(['status' => $user->status ? 0 : 1]);
        return $user;
    }

    private function get(string $group, string $key, mixed $default = null): mixed
    {
        $row = SystemSetting::query()->where('key', $key)->first();
        if (!$row) {
            return $default;
        }
        return $row->value['v'] ?? $default;
    }

    private function set(string $group, string $key, mixed $value): void
    {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'value' => ['v' => $value]]
        );
    }
}
