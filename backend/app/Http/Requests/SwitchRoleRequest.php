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
            'role' => ['required', 'in:super_admin,tenant_admin,operator'],
            'password' => ['required', 'string', 'min:6', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return [
            'role' => '目标角色',
            'password' => '登录密码',
        ];
    }
}
