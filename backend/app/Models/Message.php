<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'session_id', 'from_type', 'content', 'read_status', 'is_blocked', 'block_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(MessageSession::class, 'session_id');
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from_type,
            'content' => $this->content,
            'readStatus' => $this->read_status ?: 'unread',
            'isBlocked' => (int) $this->is_blocked === 1,
            'blockReason' => $this->block_reason,
            'createdAt' => optional($this->created_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
