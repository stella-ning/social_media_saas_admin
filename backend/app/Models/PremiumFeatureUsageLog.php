<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PremiumFeatureUsageLog extends Model
{
    protected $table = 'premium_feature_usage_logs';

    protected $fillable = [
        'tenant_id', 'feature_key', 'feature_label', 'detail', 'used_at',
    ];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
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
            'featureKey' => $this->feature_key,
            'featureLabel' => $this->feature_label,
            'detail' => $this->detail,
            'usedAt' => optional($this->used_at)?->format('Y-m-d H:i'),
        ];
    }
}
