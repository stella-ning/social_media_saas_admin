<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 按用户角色限定数据范围
 * super_admin 可见全部；tenant_admin / operator 仅本租户
 */
class RoleScope
{
    public static function applyTenant(Builder $query, ?User $user, string $column = 'tenant_id'): Builder
    {
        if (!$user || $user->isSuperAdmin()) {
            return $query;
        }

        if ($user->tenant_id) {
            $query->where($column, $user->tenant_id);
        }

        return $query;
    }
}
