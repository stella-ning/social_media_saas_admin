<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialAccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'max:32'],
            'token' => ['nullable', 'string'],
            'cookie' => ['nullable', 'string'],
            'ip' => ['nullable', 'string', 'max:64'],
            'bindIp' => ['nullable', 'string', 'max:64'],
            'tenantId' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['nullable', 'string', 'max:64'],
        ];
    }
}
