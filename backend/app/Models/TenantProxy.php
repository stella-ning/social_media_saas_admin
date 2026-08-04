<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 租户-代理IP 分配关联 saas_tenant_proxy
 * 规则：代理归属某租户后，其他租户不可使用
 */
class TenantProxy extends Model
{
    protected $table = 'saas_tenant_proxy';

    protected $fillable = [
        'tenant_id',
        'proxy_ip_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function proxyIp(): BelongsTo
    {
        return $this->belongsTo(ProxyIp::class, 'proxy_ip_id');
    }
}
