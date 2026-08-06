<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantOrder extends Model
{
    protected $table = 'tenant_order';

    protected $fillable = [
        'tenant_id', 'order_no', 'package_code', 'package_version',
        'price_monthly', 'months', 'amount', 'status',
        'starts_at', 'expires_at', 'remark',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'price_monthly' => 'integer',
            'months' => 'integer',
            'amount' => 'integer',
        ];
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
            'orderNo' => $this->order_no,
            'tenantId' => $this->tenant_id,
            'tenant' => $this->tenant?->name,
            'packageCode' => $this->package_code,
            'packageLabel' => PackageSetting::LABEL_MAP[PackageSetting::typeFromPackageCode($this->package_code)] ?? '',
            'packageVersion' => $this->package_version,
            'priceMonthly' => $this->price_monthly,
            'months' => $this->months,
            'amount' => $this->amount,
            'status' => $this->status,
            'startsAt' => optional($this->starts_at)?->format('Y-m-d'),
            'expiresAt' => optional($this->expires_at)?->format('Y-m-d'),
            'remark' => $this->remark,
            'createdAt' => optional($this->created_at)?->format('Y-m-d H:i'),
        ];
    }
}
