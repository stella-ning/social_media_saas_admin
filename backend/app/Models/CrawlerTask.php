<?php

namespace App\Models;

use App\Support\PlatformEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 爬虫任务
 * 硬性关联 social_account_id → 运行时强制使用账号 Cookie + 专属代理 IP
 */
class CrawlerTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'platform', 'task_type', 'keywords', 'target', 'tenant_id',
        'social_account_id', 'frequency', 'status', 'today_count', 'daily_limit',
        'enable_comment_collect', 'enable_user_homepage_check', 'auto_comment_reply',
        'reply_interval', 'daily_reply_max', 'today_reply_count',
    ];

    protected function casts(): array
    {
        return [
            'today_count' => 'integer',
            'daily_limit' => 'integer',
            'enable_comment_collect' => 'integer',
            'enable_user_homepage_check' => 'integer',
            'auto_comment_reply' => 'integer',
            'reply_interval' => 'integer',
            'daily_reply_max' => 'integer',
            'today_reply_count' => 'integer',
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

    /**
     * 调度器运行载荷：账号 Cookie + 专属代理 + 浏览器指纹
     * 注意：返回含 cookie，调用方禁止写入业务日志明文
     *
     * @return array{
     *   task_id:int,
     *   social_account_id:int|null,
     *   platform:string,
     *   proxy_server_addr:string|null,
     *   proxy_ip_id:int|null,
     *   cookies:array,
     *   user_agent:string|null,
     *   viewport:string|null,
     *   keywords:string|null,
     *   task_type:string|null
     * }
     */
    public function resolveRuntimeContext(): array
    {
        $this->loadMissing(['socialAccount.proxy', 'socialAccount.activeCookie']);
        $account = $this->socialAccount;
        $proxy = $account?->proxy;
        $cookieJson = $account?->activeCookie?->cookie_json;
        $cookies = [];
        if ($cookieJson) {
            $decoded = json_decode($cookieJson, true);
            $cookies = is_array($decoded) ? $decoded : [];
        }

        $proxyAddr = null;
        if ($proxy) {
            $addr = $proxy->address;
            if ($addr && !preg_match('#^[a-z]+://#i', $addr)) {
                $scheme = str_contains(strtoupper((string) $proxy->protocol), 'SOCKS') ? 'socks5' : 'http';
                $proxyAddr = $scheme.'://'.$addr;
            } else {
                $proxyAddr = $addr;
            }
        }

        $platformKey = 'xiaohongshu';
        if ($account) {
            try {
                $platformKey = PlatformEnum::toPythonKey((int) $account->platform);
            } catch (\Throwable) {
                $platformKey = 'xiaohongshu';
            }
        }

        return [
            'task_id' => $this->id,
            'social_account_id' => $this->social_account_id,
            'account_name' => $account?->account_name,
            'platform' => $platformKey,
            'proxy_server_addr' => $proxyAddr,
            'proxy_ip_id' => $proxy?->id,
            'cookies' => $cookies,
            'user_agent' => $account?->browser_user_agent,
            'viewport' => $account?->browser_viewport ?: '1920x1080',
            'keywords' => $this->keywords,
            'task_type' => $this->task_type,
            'enable_comment_collect' => (int) ($this->enable_comment_collect ?? 1) === 1,
            'enable_user_homepage_check' => (int) ($this->enable_user_homepage_check ?? 0) === 1,
            'auto_comment_reply' => (int) ($this->auto_comment_reply ?? 0) === 1,
            'reply_interval' => (int) ($this->reply_interval ?? 90),
            'daily_reply_max' => (int) ($this->daily_reply_max ?? 30),
            'behavior' => $this->resolveBehaviorConfig(),
        ];
    }

    /** 真人行为：任务级优先，否则租户默认 */
    public function resolveBehaviorConfig(): array
    {
        $row = CrawlerHumanBehavior::query()
            ->where('tenant_id', $this->tenant_id)
            ->where(function ($q) {
                $q->where('crawler_task_id', $this->id)->orWhereNull('crawler_task_id');
            })
            ->orderByRaw('crawler_task_id is null')
            ->first();

        if (!$row) {
            return [
                'scroll_min_ms' => 800,
                'scroll_max_ms' => 2400,
                'dwell_min_ms' => 1500,
                'dwell_max_ms' => 5000,
                'interval_min_ms' => 3000,
                'interval_max_ms' => 8000,
                'enable_scroll' => true,
                'homepage_delay_min_ms' => 800,
                'homepage_delay_max_ms' => 2500,
            ];
        }
        $extra = $row->extra ?: [];

        return [
            'scroll_min_ms' => (int) $row->scroll_min_ms,
            'scroll_max_ms' => (int) $row->scroll_max_ms,
            'dwell_min_ms' => (int) $row->dwell_min_ms,
            'dwell_max_ms' => (int) $row->dwell_max_ms,
            'interval_min_ms' => (int) $row->interval_min_ms,
            'interval_max_ms' => (int) $row->interval_max_ms,
            'enable_scroll' => (bool) ($extra['enable_scroll'] ?? true),
            'homepage_delay_min_ms' => (int) ($extra['homepage_delay_min_ms'] ?? 800),
            'homepage_delay_max_ms' => (int) ($extra['homepage_delay_max_ms'] ?? 2500),
            'fingerprint_preset' => $row->fingerprint_preset,
        ];
    }

    public function toFrontendArray(): array
    {
        $this->loadMissing(['tenant', 'socialAccount.proxy']);
        $proxy = $this->socialAccount?->proxy;

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
            'socialAccountName' => $this->socialAccount?->display_name
                ?: $this->socialAccount?->account_name,
            'boundProxyIp' => $proxy?->address,
            'frequency' => $this->frequency,
            'status' => $this->status,
            'todayCount' => $this->today_count,
            'dailyLimit' => $this->daily_limit,
            'enableCommentCollect' => (int) ($this->enable_comment_collect ?? 1) === 1,
            'enableUserHomepageCheck' => (int) ($this->enable_user_homepage_check ?? 0) === 1,
            'autoCommentReply' => (int) ($this->auto_comment_reply ?? 0) === 1,
            'replyInterval' => (int) ($this->reply_interval ?? 90),
            'dailyReplyMax' => (int) ($this->daily_reply_max ?? 30),
            'todayReplyCount' => (int) ($this->today_reply_count ?? 0),
        ];
    }
}
