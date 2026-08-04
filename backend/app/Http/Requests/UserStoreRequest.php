<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 新增系统用户请求验证
 */
class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tenantId') && !$this->has('tenant_id')) {
            $this->merge(['tenant_id' => $this->input('tenantId')]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6', 'max:128'],
            'role' => ['required', 'string', 'in:super_admin,tenant_admin,operator'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => '请输入登录账号',
            'username.unique' => '账号已存在',
            'password.required' => '请输入密码',
            'password.min' => '密码至少 6 位',
            'role.required' => '请选择角色',
            'role.in' => '角色类型无效',
            'tenant_id.exists' => '租户不存在',
        ];
    }
}
