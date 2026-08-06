<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 消息会话
 * 链路：社媒账号收消息 → 会话 → AI/人工应答 → 高意向推 CRM
 */
class MessageSession extends Model
{
    use HasFactory, SoftDeletes;

    public const INTENT_NONE = 'none';
    public const INTENT_NORMAL = 'normal';
    public const INTENT_HIGH = 'high';

    public const HANDLE_UNREAD = 'unread';
    public const HANDLE_READ = 'read';
    public const HANDLE_PROCESSED = 'processed';

    protected $fillable = [
        'name', 'avatar', 'platform', 'tenant_id', 'social_account_id',
        'last_msg', 'time_label', 'unread', 'date_label',
        'ai_auto_reply', 'human_takeover', 'session_status', 'handle_status',
        'intent_level', 'consult_product', 'tags',
        'last_visitor_at', 'closed_at', 'crm_lead_id',
    ];

    protected function casts(): array
    {
        return [
            'unread' => 'integer',
            'ai_auto_reply' => 'boolean',
            'human_takeover' => 'integer',
            'tags' => 'array',
            'last_visitor_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'session_id')->orderBy('id');
    }

    public static function intentLabel(string $level): string
    {
        return match ($level) {
            self::INTENT_HIGH => '高意向',
            self::INTENT_NORMAL => '普通意向',
            default => '无意向',
        };
    }

    public function toFrontendArray(): array
    {
        $tags = $this->tags ?: [];
        if ($this->intent_level) {
            $tags = array_values(array_unique(array_merge($tags, [self::intentLabel($this->intent_level)])));
        }
        if ($this->consult_product) {
            $tags = array_values(array_unique(array_merge($tags, [$this->consult_product])));
        }
        if ($this->platform) {
            $tags = array_values(array_unique(array_merge($tags, [$this->platform])));
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'platform' => $this->platform,
            'tenant' => $this->tenant?->name,
            'tenantId' => $this->tenant_id,
            'socialAccountId' => $this->social_account_id,
            'socialAccountName' => $this->socialAccount?->display_name ?: $this->socialAccount?->account_name,
            'lastMsg' => $this->last_msg,
            'time' => $this->time_label,
            'unread' => $this->unread,
            'dateLabel' => $this->date_label,
            'aiAutoReply' => (bool) $this->ai_auto_reply,
            'humanTakeover' => (int) $this->human_takeover === 1,
            'sessionStatus' => $this->session_status ?: 'open',
            'handleStatus' => $this->handle_status ?: 'unread',
            'intentLevel' => $this->intent_level ?: 'none',
            'intentLabel' => self::intentLabel($this->intent_level ?: 'none'),
            'consultProduct' => $this->consult_product,
            'tags' => $tags,
            'crmLeadId' => $this->crm_lead_id,
            'closedAt' => optional($this->closed_at)?->format('Y-m-d H:i'),
            'messages' => $this->relationLoaded('messages')
                ? $this->messages->map->toFrontendArray()->values()->all()
                : [],
        ];
    }
}
