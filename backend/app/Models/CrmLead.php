<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmLead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nickname', 'phone', 'quote', 'channel', 'tenant_id', 'score',
        'intent', 'status', 'follower', 'tags', 'remark',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'score' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'phone' => $this->phone,
            'quote' => $this->quote,
            'channel' => $this->channel,
            'tenant' => $this->tenant?->name,
            'tenantId' => $this->tenant_id,
            'score' => $this->score,
            'intent' => $this->intent,
            'status' => $this->status,
            'follower' => $this->follower,
            'tags' => $this->tags ?? [],
            'remark' => $this->remark,
        ];
    }
}
