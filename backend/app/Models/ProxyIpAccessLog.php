<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyIpAccessLog extends Model
{
    protected $table = 'proxy_ip_access_logs';

    protected $fillable = [
        'proxy_ip_id', 'tenant_id', 'crawler_task_id',
        'action', 'result', 'detail', 'logged_at',
    ];

    protected function casts(): array
    {
        return ['logged_at' => 'datetime'];
    }

    public function proxyIp(): BelongsTo
    {
        return $this->belongsTo(ProxyIp::class, 'proxy_ip_id');
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'proxyIpId' => $this->proxy_ip_id,
            'tenantId' => $this->tenant_id,
            'crawlerTaskId' => $this->crawler_task_id,
            'action' => $this->action,
            'result' => $this->result,
            'detail' => $this->detail,
            'time' => optional($this->logged_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
