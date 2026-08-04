<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;

/**
 * 租户数据范围：非超管按自身 tenant_id 过滤
 */
trait ScopesTenantData
{
    protected function scopeTenantId(?User $user): ?int
    {
        if (!$user || $user->isSuperAdmin()) {
            return null;
        }
        return $user->tenant_id;
    }
}
