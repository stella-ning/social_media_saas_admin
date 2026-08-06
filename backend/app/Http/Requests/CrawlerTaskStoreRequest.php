<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrawlerTaskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('social_account_id') && !$this->has('socialAccountId')) {
            $merge['socialAccountId'] = $this->input('social_account_id');
        }
        if ($this->has('tenant_id') && !$this->has('tenantId')) {
            $merge['tenantId'] = $this->input('tenant_id');
        }
        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'platform' => ['nullable', 'string', 'max:32'],
            'taskType' => ['required', 'in:keyword,monitor'],
            'keywords' => ['required', 'string'],
            'frequency' => ['required', 'string', 'max:32'],
            'dailyLimit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'tenantId' => ['required', 'integer', 'exists:tenants,id'],
            'socialAccountId' => ['required', 'integer', 'exists:saas_social_account,id'],
            'social_account_id' => ['nullable', 'integer', 'exists:saas_social_account,id'],
            'enableCommentCollect' => ['nullable', 'boolean'],
            'enableUserHomepageCheck' => ['nullable', 'boolean'],
            'autoCommentReply' => ['nullable', 'boolean'],
            'replyInterval' => ['nullable', 'integer', 'min:30', 'max:3600'],
            'dailyReplyMax' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'socialAccountId.required' => '请选择执行社媒账号',
            'socialAccountId.exists' => '所选社媒账号不存在',
            'tenantId.required' => '请选择所属租户',
        ];
    }
}
