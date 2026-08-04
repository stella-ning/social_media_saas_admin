<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlerTaskLog extends Model
{
    protected $fillable = ['task_id', 'type', 'content', 'logged_at'];

    protected function casts(): array
    {
        return ['logged_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CrawlerTask::class, 'task_id');
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'content' => $this->content,
            'time' => optional($this->logged_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
