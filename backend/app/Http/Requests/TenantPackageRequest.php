<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package' => ['required', 'in:basic,pro,ent'],
            'concurrent' => ['required', 'integer', 'min:1', 'max:100'],
            'aiQuota' => ['required', 'integer', 'min:1000', 'max:100000'],
            'binds' => ['required', 'integer', 'min:1', 'max:50'],
            'kb' => ['required', 'numeric', 'min:0.5', 'max:100'],
        ];
    }
}
