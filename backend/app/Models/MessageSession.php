<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'avatar', 'platform', 'tenant_id', 'last_msg', 'time_label',
        'unread', 'date_label', 'ai_auto_reply',
    ];

    protected function casts(): array
    {
        return [
            'unread' => 'integer',
            'ai_auto_reply' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'session_id')->orderBy('id');
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'platform' => $this->platform,
            'tenant' => $this->tenant?->name,
            'tenantId' => $this->tenant_id,
            'lastMsg' => $this->last_msg,
            'time' => $this->time_label,
            'unread' => $this->unread,
            'dateLabel' => $this->date_label,
            'aiAutoReply' => $this->ai_auto_reply,
            'messages' => $this->relationLoaded('messages')
                ? $this->messages->map->toFrontendArray()->values()->all()
                : [],
        ];
    }
}
