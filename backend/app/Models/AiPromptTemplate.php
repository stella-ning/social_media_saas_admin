<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiPromptTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'category', 'tag_type', 'name', 'desc', 'role', 'rules', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'integer',
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
            'category' => $this->category,
            'tagType' => $this->tag_type,
            'name' => $this->name,
            'desc' => $this->desc,
            'role' => $this->role,
            'rules' => $this->rules,
            'isDefault' => (int) ($this->is_default ?? 0) === 1,
            'updateTime' => optional($this->updated_at)?->format('Y-m-d'),
        ];
    }
}
