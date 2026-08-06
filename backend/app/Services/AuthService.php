<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 登录鉴权服务
 */
class AuthService
{
    /**
     * 账号密码登录，返回用户 + token
     */
    public function login(string $username, string $password): array
    {
        $user = User::query()
            ->with('tenant')
            ->where('username', $username)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['账号或密码错误'],
            ]);
        }

        if ((int) $user->status !== 1) {
            throw ValidationException::withMessages([
                'username' => ['账号已被禁用'],
            ]);
        }

        // 清理旧 token，单端登录演示
        $user->tokens()->delete();
        $token = $user->createToken('api')->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $token,
            'tokenType' => 'Bearer',
            'user' => $this->formatUser($user),
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * 切换账号：超管输入目标用户名 + 密码后换发 token
     *
     * @param  string|null  $expectRole  可选期望角色，不一致时仍以账号真实角色登录
     */
    public function switchRole(User $current, string $username, string $password, ?string $expectRole = null): array
    {
        if (!$current->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => ['仅超级管理员可切换角色'],
            ]);
        }

        $target = User::query()
            ->with('tenant')
            ->where('username', $username)
            ->first();

        if (!$target) {
            throw ValidationException::withMessages([
                'username' => ['目标账号不存在'],
            ]);
        }

        if ((int) $target->status !== 1) {
            throw ValidationException::withMessages([
                'username' => ['目标账号已被禁用'],
            ]);
        }

        if (!Hash::check($password, $target->password)) {
            throw ValidationException::withMessages([
                'password' => ["密码错误，请输入账号 {$target->username} 的正确密码"],
            ]);
        }

        if ($expectRole && $target->role !== $expectRole) {
            // 仍允许切换，真实角色以账号为准（前端可提示 roleMatched=false）
        }

        $current->tokens()->delete();
        $token = $target->createToken('api')->plainTextToken;
        $target->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $token,
            'tokenType' => 'Bearer',
            'user' => $this->formatUser($target),
            'roleMatched' => !$expectRole || $target->role === $expectRole,
        ];
    }

    public function formatUser(User $user): array
    {
        $labels = config('permission.roles');
        $tagMap = [
            'super_admin' => 'primary',
            'tenant_admin' => 'success',
            'operator' => 'warning',
        ];
        $contextMap = [
            'super_admin' => '(当前查看：全平台)',
            'tenant_admin' => $user->tenant
                ? '(当前查看：'.$user->tenant->name.')'
                : '(当前查看：本租户)',
            'operator' => '(当前查看：个人跟进数据)',
        ];

        return [
            'id' => $user->id,
            'username' => $user->username,
            'displayName' => $user->display_name,
            'role' => $user->role,
            'roleLabel' => $labels[$user->role] ?? $user->role,
            'roleTagType' => $tagMap[$user->role] ?? 'info',
            'tenantId' => $user->tenant_id,
            'tenant' => $user->tenant?->name ?? '',
            'package' => $user->tenant?->package,
            'packageExpiresAt' => optional($user->tenant?->package_expires_at)?->format('Y-m-d'),
            'dedicatedIpPoolEnabled' => (int) ($user->tenant?->dedicated_ip_pool_enabled ?? 0) === 1,
            'ipRotateEnabled' => (int) ($user->tenant?->ip_rotate_enabled ?? 0) === 1,
            'context' => $contextMap[$user->role] ?? '',
            'permissions' => config('permission.permissions.'.$user->role, []),
            'lastLogin' => optional($user->last_login_at)?->format('Y-m-d H:i'),
            'status' => $user->status,
        ];
    }
}
