<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 消息敏感词告警日志 saas_message_alert_logs */
class MessageAlertLog extends Model
{
    protected $table = 'saas_message_alert_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'session_id', 'message_id', 'hit_words', 'content_snippet', 'action', 'logged_at',
    ];

    protected function casts(): array
    {
        return ['logged_at' => 'datetime'];
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'sessionId' => $this->session_id,
            'messageId' => $this->message_id,
            'hitWords' => $this->hit_words,
            'contentSnippet' => $this->content_snippet,
            'action' => $this->action,
            'loggedAt' => optional($this->logged_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
