<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'uid', 'avatar', 'platform', 'bind_ip', 'tenant_id', 'status', 'cookie',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'uid' => $this->uid,
            'avatar' => $this->avatar,
            'platform' => $this->platform,
            'bindIp' => $this->bind_ip,
            'tenant' => $this->tenant?->name,
            'tenantId' => $this->tenant_id,
            'status' => $this->status,
        ];
    }
}
