<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:6', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => '账号',
            'password' => '密码',
        ];
    }
}
