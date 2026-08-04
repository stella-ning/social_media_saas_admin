<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrawlerTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'platform', 'task_type', 'keywords', 'target', 'tenant_id',
        'social_account_id', 'frequency', 'status', 'today_count', 'daily_limit',
    ];

    protected function casts(): array
    {
        return [
            'today_count' => 'integer',
            'daily_limit' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CrawlerTaskLog::class, 'task_id')->orderByDesc('logged_at');
    }

    /**
     * 爬虫运行时强制读取绑定账号的专属代理（全程不可更换）
     */
    public function resolveBoundProxy(): ?ProxyIp
    {
        $account = $this->socialAccount()->with('proxy')->first();
        return $account?->proxy;
    }

    public function toFrontendArray(): array
    {
        $proxy = $this->resolveBoundProxy();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'taskType' => $this->task_type,
            'keywords' => $this->keywords,
            'target' => $this->target,
            'tenant' => $this->tenant?->name,
            'tenantId' => $this->tenant_id,
            'socialAccountId' => $this->social_account_id,
            'boundProxyIp' => $proxy?->address,
            'frequency' => $this->frequency,
            'status' => $this->status,
            'todayCount' => $this->today_count,
            'dailyLimit' => $this->daily_limit,
        ];
    }
}
