<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantSubAccount extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_sub_account';

    protected $fillable = [
        'tenant_id', 'user_id', 'username', 'display_name',
        'role', 'permissions', 'status',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'status' => 'integer',
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
            'tenantId' => $this->tenant_id,
            'userId' => $this->user_id,
            'username' => $this->username,
            'displayName' => $this->display_name,
            'role' => $this->role,
            'roleLabel' => match ($this->role) {
                'cs' => '客服',
                'crawler_ops' => '爬虫运维',
                default => '业务员',
            },
            'permissions' => $this->permissions ?: [],
            'status' => (int) $this->status,
            'createdAt' => optional($this->created_at)?->format('Y-m-d H:i'),
        ];
    }
}
