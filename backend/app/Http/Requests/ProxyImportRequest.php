<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProxyImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'list' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:64'],
        ];
    }
}
