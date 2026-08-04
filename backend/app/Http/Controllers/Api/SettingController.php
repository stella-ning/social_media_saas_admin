<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SettingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private SettingService $service)
    {
    }

    public function basic()
    {
        return ApiResponse::success($this->service->getBasic());
    }

    public function saveBasic(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'copyright' => ['nullable', 'string', 'max:255'],
            'notify' => ['nullable', 'boolean'],
        ]);
        return ApiResponse::success($this->service->saveBasic($data), '基本设置已保存');
    }

    public function security()
    {
        return ApiResponse::success($this->service->getSecurity());
    }

    public function saveSecurity(Request $request)
    {
        return ApiResponse::success($this->service->saveSecurity($request->all()), '安全设置已保存');
    }

    public function users()
    {
        return ApiResponse::success($this->service->users());
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:64', 'unique:users,username'],
            'displayName' => ['nullable', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:super_admin,tenant_admin,operator'],
            'tenantId' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);
        $user = $this->service->createUser($data);
        return ApiResponse::success(['id' => $user->id, 'username' => $user->username], '用户创建成功');
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['nullable', 'in:super_admin,tenant_admin,operator'],
            'tenantId' => ['nullable', 'integer', 'exists:tenants,id'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);
        $user = $this->service->updateUser($user, $data);
        return ApiResponse::success(['id' => $user->id], '用户信息已更新');
    }

    public function toggleUser(User $user)
    {
        $user = $this->service->toggleUser($user);
        return ApiResponse::success([
            'id' => $user->id,
            'status' => $user->status,
        ], $user->status ? '已启用' : '已禁用');
    }
}
