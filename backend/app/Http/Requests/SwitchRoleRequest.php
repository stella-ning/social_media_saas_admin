<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:6', 'max:64'],
            // 可选：期望角色，仅作校验提示，以账号真实角色为准
            'role' => ['nullable', 'in:super_admin,tenant_admin,operator'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => '用户名',
            'password' => '登录密码',
            'role' => '目标角色',
        ];
    }
}
