<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeDoc extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'size', 'status', 'tags', 'icon_color', 'file_path',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'name' => $this->name,
            'size' => $this->size,
            'status' => $this->status,
            'tags' => $this->tags,
            'iconColor' => $this->icon_color,
            'uploadTime' => optional($this->created_at)?->format('Y-m-d'),
        ];
    }
}
