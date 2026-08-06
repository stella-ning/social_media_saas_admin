<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantResourceConsume extends Model
{
    protected $table = 'tenant_resource_consume';

    protected $fillable = [
        'stat_date', 'tenant_id', 'package_code',
        'proxy_request_count', 'ai_call_count',
        'ai_token_cost', 'proxy_ip_cost', 'server_cost',
        'third_party_cost', 'ops_labor_cost', 'revenue',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'ai_token_cost' => 'float',
            'proxy_ip_cost' => 'float',
            'server_cost' => 'float',
            'third_party_cost' => 'float',
            'ops_labor_cost' => 'float',
            'revenue' => 'float',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function totalCost(): float
    {
        return (float) $this->ai_token_cost
            + (float) $this->proxy_ip_cost
            + (float) $this->server_cost
            + (float) $this->third_party_cost
            + (float) $this->ops_labor_cost;
    }

    public function toFrontendArray(): array
    {
        $this->loadMissing('tenant');
        $cost = $this->totalCost();
        $rev = (float) $this->revenue;

        return [
            'id' => $this->id,
            'statDate' => optional($this->stat_date)?->format('Y-m-d'),
            'tenantId' => $this->tenant_id,
            'tenant' => $this->tenant?->name ?? '平台汇总',
            'packageCode' => $this->package_code,
            'proxyRequestCount' => (int) $this->proxy_request_count,
            'aiCallCount' => (int) $this->ai_call_count,
            'aiTokenCost' => round((float) $this->ai_token_cost, 2),
            'proxyIpCost' => round((float) $this->proxy_ip_cost, 2),
            'serverCost' => round((float) $this->server_cost, 2),
            'thirdPartyCost' => round((float) $this->third_party_cost, 2),
            'opsLaborCost' => round((float) $this->ops_labor_cost, 2),
            'totalCost' => round($cost, 2),
            'revenue' => round($rev, 2),
            'netProfit' => round($rev - $cost, 2),
            'grossMargin' => $rev > 0 ? round(($rev - $cost) / $rev * 100, 1) : 0,
        ];
    }
}
