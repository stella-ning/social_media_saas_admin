<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFollowReminder extends Model
{
    protected $table = 'crm_follow_reminders';

    protected $fillable = [
        'tenant_id', 'crm_lead_id', 'title', 'remind_at', 'status', 'channel',
    ];

    protected function casts(): array
    {
        return ['remind_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function toFrontendArray(): array
    {
        $this->loadMissing('lead');

        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'crmLeadId' => $this->crm_lead_id,
            'leadName' => $this->lead?->nickname,
            'title' => $this->title,
            'remindAt' => optional($this->remind_at)?->format('Y-m-d H:i'),
            'status' => $this->status,
            'channel' => $this->channel,
        ];
    }
}
