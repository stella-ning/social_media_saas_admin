<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 绑定新社媒账号（凭据自动登录，不再接收 Cookie）
 */
class SocialAccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('tenantId') && !$this->has('tenant_id')) {
            $merge['tenant_id'] = $this->input('tenantId');
        }
        if ($this->has('accountName') && !$this->has('account_name')) {
            $merge['account_name'] = $this->input('accountName');
        }
        if ($this->has('proxyIpId') && !$this->has('proxy_ip_id')) {
            $merge['proxy_ip_id'] = $this->input('proxyIpId');
        }
        if ($this->has('verifyCode') && !$this->has('code')) {
            $merge['code'] = $this->input('verifyCode');
        }
        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'platform' => ['required'],
            'account_name' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'string', 'max:16'],
            'proxy_ip_id' => ['required', 'integer', 'exists:proxy_ips,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required' => '请选择所属租户',
            'platform.required' => '请选择平台',
            'account_name.required' => '请输入登录账号/手机号',
            'password.required' => '请输入登录密码',
            'proxy_ip_id.required' => '请选择空闲代理 IP',
            'proxy_ip_id.exists' => '代理 IP 不存在',
        ];
    }
}
