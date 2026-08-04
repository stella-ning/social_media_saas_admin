<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * 按角色校验模块访问权限
 * 用法：middleware('role.permission:tenants')
 */
class EnsureRolePermission
{
    public function handle(Request $request, Closure $next, string $module)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }

        if (!$user->canAccessModule($module)) {
            return ApiResponse::forbidden('当前角色无权访问该模块');
        }

        return $next($request);
    }
}
