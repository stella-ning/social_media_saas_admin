<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CRM 线索打标请求验证
 */
class CrmLeadTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('level') && !$this->has('intent')) {
            $this->merge(['intent' => $this->input('level')]);
        }
    }

    public function rules(): array
    {
        return [
            'level' => ['nullable', 'string', 'in:high,mid,low'],
            'intent' => ['nullable', 'string', 'in:high,mid,low'],
            'status' => ['nullable', 'string', 'max:32'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:32'],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'level.in' => '意向等级无效',
            'intent.in' => '意向等级无效',
        ];
    }
}
