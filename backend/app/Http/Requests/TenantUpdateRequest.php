<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 更新租户请求验证
 */
class TenantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:128'],
            'contact' => ['sometimes', 'required', 'string', 'max:64'],
            'package' => ['sometimes', 'nullable', 'string', 'in:basic,pro,ent'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:128'],
            'remark' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入租户名称',
            'contact.required' => '请输入联系人',
            'package.in' => '套餐类型无效',
            'email.email' => '邮箱格式不正确',
        ];
    }
}
