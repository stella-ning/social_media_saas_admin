<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 平台全局默认 AI 参数 saas_ai_param_global（优先级最低）
 */
class AiParamGlobal extends Model
{
    protected $table = 'saas_ai_param_global';

    protected $fillable = [
        'ai_model',
        'encrypt_api_key',
        'api_base_url',
        'temperature',
        'max_tokens',
        'top_p',
        'daily_call_quota',
    ];

    protected $hidden = ['encrypt_api_key'];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'top_p' => 'float',
            'max_tokens' => 'integer',
            'daily_call_quota' => 'integer',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }
}
