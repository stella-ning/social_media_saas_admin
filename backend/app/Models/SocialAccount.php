<?php

namespace App\Models;

use App\Support\PlatformEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 社媒账号主表 saas_social_account
 * 密码字段 encrypt_pwd 为 AES 密文，禁止序列化到日志/API
 */
class SocialAccount extends Model
{
    protected $table = 'saas_social_account';

    protected $fillable = [
        'tenant_id',
        'platform',
        'account_name',
        'encrypt_pwd',
        'bind_proxy_id',
        'browser_user_agent',
        'browser_viewport',
        'account_status',
        'last_refresh_cookie',
        'login_fail_count',
        'risk_tip',
        'display_name',
        'avatar',
        'bind_param_template_id',
        'bind_prompt_id',
        'enable_account_knowledge',
    ];

    protected $hidden = [
        'encrypt_pwd',
    ];

    protected function casts(): array
    {
        return [
            'platform' => 'integer',
            'account_status' => 'integer',
            'login_fail_count' => 'integer',
            'last_refresh_cookie' => 'datetime',
            'enable_account_knowledge' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(ProxyIp::class, 'bind_proxy_id');
    }

    public function paramTemplate(): BelongsTo
    {
        return $this->belongsTo(AiParamTemplate::class, 'bind_param_template_id');
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(AiPromptTemplate::class, 'bind_prompt_id');
    }

    public function knowledgeRels(): HasMany
    {
        return $this->hasMany(AccountKnowledgeRel::class, 'social_account_id');
    }

    public function cookies(): HasMany
    {
        return $this->hasMany(AccountCookie::class, 'social_account_id');
    }

    public function activeCookie(): HasOne
    {
        return $this->hasOne(AccountCookie::class, 'social_account_id')
            ->where('expire_status', 1)
            ->latestOfMany('id');
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(AccountOperationLog::class, 'social_account_id');
    }

    public function isOnline(): bool
    {
        return (int) $this->account_status === 1;
    }

    public function isXiaohongshu(): bool
    {
        return (int) $this->platform === PlatformEnum::XHS;
    }

    /** 对齐前端列表字段（不含 cookie / 密码） */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->display_name ?: $this->account_name,
            'uid' => $this->account_name,
            'accountName' => $this->account_name,
            'avatar' => $this->avatar ?: 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
            'platform' => PlatformEnum::toLabel((int) $this->platform),
            'platformCode' => (int) $this->platform,
            'bindIp' => $this->proxy?->address ?? '',
            'bindProxyId' => $this->bind_proxy_id,
            'tenant' => $this->tenant?->name,
            'tenantId' => $this->tenant_id,
            'status' => $this->isOnline() ? 'online' : 'offline',
            'accountStatus' => (int) $this->account_status,
            'lastRefreshCookie' => optional($this->last_refresh_cookie)?->format('Y-m-d H:i:s'),
            'riskTip' => $this->risk_tip,
            'loginFailCount' => (int) $this->login_fail_count,
            'supportAccountAiConfig' => $this->isXiaohongshu(),
            'bindParamTemplateId' => $this->bind_param_template_id,
            'bindPromptId' => $this->bind_prompt_id,
            'enableAccountKnowledge' => (int) $this->enable_account_knowledge === 1,
        ];
    }
}
