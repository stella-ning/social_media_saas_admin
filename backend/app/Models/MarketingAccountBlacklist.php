<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 营销号黑名单：主页核验判定后写入，后续爬虫直接跳过 */
class MarketingAccountBlacklist extends Model
{
    protected $table = 'marketing_account_blacklist';

    protected $fillable = [
        'tenant_id', 'platform', 'platform_user_id', 'nickname',
        'homepage_url', 'reason', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function toFrontendArray(): array
    {
        $this->loadMissing('tenant');

        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'tenant' => $this->tenant?->name,
            'platform' => $this->platform,
            'platformUserId' => $this->platform_user_id,
            'nickname' => $this->nickname,
            'homepageUrl' => $this->homepage_url,
            'reason' => $this->reason,
            'meta' => $this->meta ?: [],
            'createdAt' => optional($this->created_at)?->format('Y-m-d H:i'),
        ];
    }
}
