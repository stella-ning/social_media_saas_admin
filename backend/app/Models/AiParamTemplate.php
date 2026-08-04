<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 租户 AI 参数模板 saas_ai_param_template
 * API-Key 以 AES 密文存储，禁止序列化到日志/API 明文
 */
class AiParamTemplate extends Model
{
    protected $table = 'saas_ai_param_template';

    protected $fillable = [
        'tenant_id',
        'template_name',
        'ai_model',
        'encrypt_api_key',
        'api_base_url',
        'temperature',
        'max_tokens',
        'top_p',
        'daily_call_quota',
        'is_default',
        'template_level',
    ];

    protected $hidden = [
        'encrypt_api_key',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'top_p' => 'float',
            'max_tokens' => 'integer',
            'daily_call_quota' => 'integer',
            'is_default' => 'integer',
            'template_level' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** 前端列表（脱敏：仅返回是否已配置 Key） */
    public function toFrontendArray(bool $maskKey = true): array
    {
        $level = (int) ($this->template_level ?: 1);

        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'templateName' => $this->template_name,
            'aiModel' => $this->ai_model,
            'apiBaseUrl' => $this->api_base_url,
            'temperature' => (float) $this->temperature,
            'maxTokens' => (int) $this->max_tokens,
            'topP' => (float) $this->top_p,
            'dailyCallQuota' => (int) $this->daily_call_quota,
            'isDefault' => (int) $this->is_default === 1,
            'templateLevel' => $level,
            'templateLevelLabel' => \App\Support\PackageTemplateLevel::levelLabel($level),
            'hasApiKey' => !empty($this->encrypt_api_key),
            'apiKeyMasked' => $maskKey && $this->encrypt_api_key ? '********' : '',
            'updatedAt' => optional($this->updated_at)?->format('Y-m-d H:i'),
        ];
    }
}
