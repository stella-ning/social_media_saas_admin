<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProxyIp extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proxy_ips';

    protected $fillable = [
        'address', 'location', 'protocol', 'status', 'load', 'capacity', 'tenant_id', 'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'load' => 'integer',
            'capacity' => 'integer',
            'latency_ms' => 'integer',
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
            'address' => $this->address,
            'location' => $this->location,
            'protocol' => $this->protocol,
            'status' => $this->status,
            'load' => $this->load,
            'capacity' => $this->capacity,
            'tenantId' => $this->tenant_id,
            'latencyMs' => $this->latency_ms,
        ];
    }
}
