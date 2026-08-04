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

    /** 切换角色：校验目标角色账号密码后换发 token */
    public function switchRole(User $current, string $targetRole, string $password): array
    {
        $target = User::query()
            ->with('tenant')
            ->where('role', $targetRole)
            ->where('status', 1)
            ->first();

        if (!$target) {
            throw ValidationException::withMessages([
                'role' => ['目标角色账号不存在'],
            ]);
        }

        if (!Hash::check($password, $target->password)) {
            throw ValidationException::withMessages([
                'password' => ["密码错误，请输入账号 {$target->username} 的正确密码"],
            ]);
        }

        $current->tokens()->delete();
        $token = $target->createToken('api')->plainTextToken;
        $target->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $token,
            'tokenType' => 'Bearer',
            'user' => $this->formatUser($target),
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
            'super_admin' => '',
            'tenant_admin' => $user->tenant
                ? '(当前查看：'.$user->tenant->name.')'
                : '(当前查看：广州悦享科技有限公司)',
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
            'context' => $contextMap[$user->role] ?? '',
            'permissions' => config('permission.permissions.'.$user->role, []),
            'lastLogin' => optional($user->last_login_at)?->format('Y-m-d H:i'),
            'status' => $user->status,
        ];
    }
}
