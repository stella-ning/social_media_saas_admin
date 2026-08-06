<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 评论操作日志 spider_comment_record
 * 全链路：采集 → 意向筛选 → 主页核验 → AI回复 → 敏感词 → 发送 → CRM
 */
class SpiderCommentRecord extends Model
{
    protected $table = 'spider_comment_record';

    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'task_id', 'tenant_id', 'note_url', 'comment_user_name', 'comment_user_id',
        'comment_content', 'user_homepage_url', 'homepage_check_result', 'homepage_meta',
        'is_inquiry', 'funnel_stage', 'ai_reply_content', 'sensitive_word_check_status',
        'hit_sensitive_words', 'operate_social_account', 'run_status',
        'message_session_id', 'crm_lead_id', 'fail_reason', 'create_time',
    ];

    protected function casts(): array
    {
        return [
            'homepage_meta' => 'array',
            'is_inquiry' => 'boolean',
            'create_time' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CrawlerTask::class, 'task_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'operate_social_account');
    }

    public function toFrontendArray(): array
    {
        $this->loadMissing(['tenant', 'socialAccount', 'task']);

        return [
            'id' => $this->id,
            'taskId' => $this->task_id,
            'taskName' => $this->task?->name,
            'tenantId' => $this->tenant_id,
            'tenant' => $this->tenant?->name,
            'noteUrl' => $this->note_url,
            'commentUserName' => $this->comment_user_name,
            'commentUserId' => $this->comment_user_id,
            'commentContent' => $this->comment_content,
            'userHomepageUrl' => $this->user_homepage_url,
            'homepageCheckResult' => $this->homepage_check_result,
            'homepageCheckLabel' => $this->homepageResultLabel(),
            'homepageMeta' => $this->homepage_meta ?: [],
            'isInquiry' => (bool) $this->is_inquiry,
            'funnelStage' => $this->funnel_stage,
            'funnelStageLabel' => $this->funnelStageLabel(),
            'aiReplyContent' => $this->ai_reply_content,
            'sensitiveWordCheckStatus' => $this->sensitive_word_check_status,
            'hitSensitiveWords' => $this->hit_sensitive_words,
            'operateSocialAccount' => $this->operate_social_account,
            'operateAccountName' => $this->socialAccount?->display_name
                ?: $this->socialAccount?->account_name,
            'runStatus' => $this->run_status,
            'messageSessionId' => $this->message_session_id,
            'crmLeadId' => $this->crm_lead_id,
            'failReason' => $this->fail_reason,
            'createTime' => optional($this->create_time)?->format('Y-m-d H:i:s'),
        ];
    }

    private function homepageResultLabel(): string
    {
        return match ($this->homepage_check_result) {
            'real_consumer' => '真实消费者',
            'marketing' => '营销商家号',
            'unknown' => '无法判定',
            'skipped' => '未核验',
            'pending' => '待核验',
            default => $this->homepage_check_result ?: '—',
        };
    }

    private function funnelStageLabel(): string
    {
        return match ($this->funnel_stage) {
            'collected' => '已采集',
            'filtered' => '已过滤(非咨询)',
            'homepage_failed' => '主页核验未通过',
            'blacklisted' => '黑名单跳过',
            'ready_reply' => '待回复',
            'replied' => '已回复',
            'sensitive_block' => '敏感词拦截',
            'failed' => '失败',
            'crm_pushed' => '已入CRM',
            default => $this->funnel_stage ?: '—',
        };
    }
}
