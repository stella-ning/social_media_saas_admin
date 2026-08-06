<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 租户快捷回复话术库 saas_quick_replies */
class QuickReply extends Model
{
    protected $table = 'saas_quick_replies';

    protected $fillable = [
        'tenant_id', 'title', 'content', 'category', 'sort',
    ];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
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
            'title' => $this->title,
            'content' => $this->content,
            'category' => $this->category ?: '常用',
            'sort' => (int) $this->sort,
        ];
    }
}
