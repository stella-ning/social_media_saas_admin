<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SwitchRoleRequest;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * 登录鉴权接口
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    /** POST /api/auth/login */
    public function login(LoginRequest $request)
    {
        $data = $this->authService->login(
            $request->input('username'),
            $request->input('password')
        );
        return ApiResponse::success($data, '登录成功');
    }

    /** POST /api/auth/logout */
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return ApiResponse::success(null, '已退出登录');
    }

    /** GET /api/auth/me */
    public function me(Request $request)
    {
        $user = $request->user()->load('tenant');
        return ApiResponse::success($this->authService->formatUser($user));
    }

    /** POST /api/auth/switch-role 超管按用户名切换账号 */
    public function switchRole(SwitchRoleRequest $request)
    {
        $data = $this->authService->switchRole(
            $request->user(),
            $request->input('username'),
            $request->input('password'),
            $request->input('role')
        );
        return ApiResponse::success($data, '账号切换成功');
    }
}
