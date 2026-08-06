<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 租户模型
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'contact', 'phone', 'email', 'package', 'status',
        'concurrent', 'ai_quota', 'binds', 'kb', 'remark',
        'max_proxy_ip', 'daily_proxy_request_limit', 'allow_self_proxy',
        'current_ai_param_template_id',
        'package_expires_at', 'package_version',
        'dedicated_ip_pool_enabled', 'ip_rotate_enabled', 'white_label_title',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'concurrent' => 'integer',
            'ai_quota' => 'integer',
            'binds' => 'integer',
            'kb' => 'float',
            'max_proxy_ip' => 'integer',
            'daily_proxy_request_limit' => 'integer',
            'allow_self_proxy' => 'integer',
            'package_expires_at' => 'datetime',
            'dedicated_ip_pool_enabled' => 'integer',
            'ip_rotate_enabled' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function crawlerTasks(): HasMany
    {
        return $this->hasMany(CrawlerTask::class);
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }

    public function currentAiParamTemplate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AiParamTemplate::class, 'current_ai_param_template_id');
    }

    /** 转为前端 camelCase 结构 */
    public function toFrontendArray(): array
    {
        $this->loadMissing('currentAiParamTemplate');

        $proxy = \App\Support\PackageQuota::effectiveProxyQuota($this);
        $setting = \App\Support\PackageQuota::settingForTenant($this);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact' => $this->contact,
            'phone' => $this->phone,
            'email' => $this->email,
            'package' => $this->package,
            'packageVersion' => $this->package_version ?: PackageSetting::PACKAGE_VERSION,
            'packageExpiresAt' => optional($this->package_expires_at)?->format('Y-m-d'),
            'packageExpired' => \App\Support\PackageQuota::isPackageExpired($this),
            'createTime' => optional($this->created_at)?->format('Y-m-d'),
            'status' => $this->status,
            'concurrent' => $this->concurrent,
            'aiQuota' => $this->ai_quota,
            'binds' => $this->binds,
            'kb' => $this->kb,
            'maxProxyIp' => $proxy['max_proxy_ip'],
            'dailyProxyRequestLimit' => $proxy['daily_proxy_request_limit'],
            'allowSelfProxy' => false,
            'dedicatedIpPoolEnabled' => (int) ($this->dedicated_ip_pool_enabled ?? 0) === 1,
            'ipRotateEnabled' => (int) ($this->ip_rotate_enabled ?? 0) === 1,
            'whiteLabelTitle' => $this->white_label_title,
            'enableSubAccount' => (int) ($setting->enable_sub_account ?? 0) === 1,
            'enableHumanBehavior' => (int) ($setting->enable_human_behavior ?? 0) === 1,
            'enableCrmAutoRemind' => (int) ($setting->enable_crm_auto_remind ?? 0) === 1,
            'premiumFeatures' => $setting->premium_features ?: [],
            'remark' => $this->remark,
            'currentAiParamTemplateId' => $this->current_ai_param_template_id,
            'currentAiParamTemplateName' => $this->currentAiParamTemplate?->template_name,
        ];
    }
}
