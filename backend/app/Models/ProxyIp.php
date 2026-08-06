<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProxyIp extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proxy_ips';

    protected $fillable = [
        'address', 'location', 'protocol', 'status', 'load', 'capacity', 'tenant_id', 'latency_ms',
        'pool_type', 'risk_level', 'last_risk_at', 'rotate_group', 'platform_scope',
    ];

    protected function casts(): array
    {
        return [
            'load' => 'integer',
            'capacity' => 'integer',
            'latency_ms' => 'integer',
            'platform_scope' => 'array',
            'last_risk_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class, 'bind_proxy_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ProxyIpAccessLog::class, 'proxy_ip_id')->orderByDesc('logged_at');
    }

    public function toFrontendArray(): array
    {
        $this->loadMissing('tenant');

        return [
            'id' => $this->id,
            'address' => $this->address,
            'location' => $this->location,
            'protocol' => $this->protocol,
            'status' => $this->status,
            'load' => $this->load,
            'capacity' => $this->capacity,
            'tenantId' => $this->tenant_id,
            'tenant' => $this->tenant?->name,
            'latencyMs' => $this->latency_ms,
            'poolType' => $this->pool_type ?: 'public',
            'poolTypeLabel' => ($this->pool_type === 'dedicated') ? '专属隔离池' : '平台公共池',
            'riskLevel' => $this->risk_level ?: 'low',
            'lastRiskAt' => optional($this->last_risk_at)?->format('Y-m-d H:i'),
            'rotateGroup' => $this->rotate_group,
            'platformScope' => $this->platform_scope ?: [],
        ];
    }
}
