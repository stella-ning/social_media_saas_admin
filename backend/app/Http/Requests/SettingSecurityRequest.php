<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 安全设置保存请求验证
 */
class SettingSecurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockOnFail' => ['nullable', 'boolean'],
            'pwdDays' => ['nullable', 'integer', 'min:0'],
            'twoFactor' => ['nullable', 'boolean'],
            'sessionMin' => ['nullable', 'integer', 'min:1'],
            'ipWhitelist' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'pwdDays.min' => '密码有效期不能为负数',
            'sessionMin.min' => '会话时长至少 1 分钟',
        ];
    }
}
