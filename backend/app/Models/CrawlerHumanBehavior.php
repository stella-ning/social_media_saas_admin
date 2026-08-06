<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlerHumanBehavior extends Model
{
    protected $table = 'crawler_human_behavior';

    protected $fillable = [
        'tenant_id', 'crawler_task_id',
        'scroll_min_ms', 'scroll_max_ms', 'dwell_min_ms', 'dwell_max_ms',
        'interval_min_ms', 'interval_max_ms', 'fingerprint_preset', 'extra',
    ];

    protected function casts(): array
    {
        return ['extra' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function toFrontendArray(): array
    {
        $extra = $this->extra ?: [];

        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'crawlerTaskId' => $this->crawler_task_id,
            'scrollMinMs' => (int) $this->scroll_min_ms,
            'scrollMaxMs' => (int) $this->scroll_max_ms,
            'dwellMinMs' => (int) $this->dwell_min_ms,
            'dwellMaxMs' => (int) $this->dwell_max_ms,
            'intervalMinMs' => (int) $this->interval_min_ms,
            'intervalMaxMs' => (int) $this->interval_max_ms,
            'fingerprintPreset' => $this->fingerprint_preset,
            'enableScroll' => (bool) ($extra['enable_scroll'] ?? true),
            'homepageDelayMinMs' => (int) ($extra['homepage_delay_min_ms'] ?? 800),
            'homepageDelayMaxMs' => (int) ($extra['homepage_delay_max_ms'] ?? 2500),
            'extra' => $extra,
        ];
    }
}
