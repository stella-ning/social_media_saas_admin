<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrawlerTaskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'platform' => ['required', 'string', 'max:32'],
            'taskType' => ['required', 'in:keyword,monitor'],
            'keywords' => ['required', 'string'],
            'frequency' => ['required', 'string', 'max:32'],
            'dailyLimit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'tenantId' => ['required', 'integer', 'exists:tenants,id'],
        ];
    }
}
