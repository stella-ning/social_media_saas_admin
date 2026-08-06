<?php

namespace App\Services;

use App\Models\CrawlerTask;
use App\Models\CrmLead;
use App\Models\MarketingAccountBlacklist;
use App\Models\SpiderCommentRecord;
use App\Support\MessageAiReplyEngine;
use App\Support\PackageQuota;
use App\Support\SensitiveWordFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 小红书评论引流漏斗：
 * 采集 → AI/关键词意向筛选 → 主页核验 → 真人风口语回复 → 敏感词 → CRM
 */
class CommentFunnelService
{
    public function listRecords(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 20)));

        $q = SpiderCommentRecord::query()->with(['tenant', 'socialAccount', 'task'])->orderByDesc('id');
        if ($scopeTenantId) {
            $q->where('tenant_id', $scopeTenantId);
        } elseif (!empty($filters['tenantId']) || !empty($filters['tenant_id'])) {
            $q->where('tenant_id', (int) ($filters['tenantId'] ?? $filters['tenant_id']));
        }
        if (!empty($filters['taskId']) || !empty($filters['task_id'])) {
            $q->where('task_id', (int) ($filters['taskId'] ?? $filters['task_id']));
        }
        if (!empty($filters['funnelStage']) || !empty($filters['funnel_stage'])) {
            $q->where('funnel_stage', $filters['funnelStage'] ?? $filters['funnel_stage']);
        }
        if (!empty($filters['runStatus']) || !empty($filters['run_status'])) {
            $q->where('run_status', $filters['runStatus'] ?? $filters['run_status']);
        }
        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $q->where(function ($inner) use ($kw) {
                $inner->where('comment_user_name', 'like', "%{$kw}%")
                    ->orWhere('comment_content', 'like', "%{$kw}%")
                    ->orWhere('ai_reply_content', 'like', "%{$kw}%");
            });
        }

        return $q->paginate($size, ['*'], 'page', $page);
    }

    public function funnelStats(?int $tenantId = null, ?int $taskId = null): array
    {
        $q = SpiderCommentRecord::query();
        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }
        if ($taskId) {
            $q->where('task_id', $taskId);
        }
        $stages = (clone $q)->selectRaw('funnel_stage, count(*) as c')->groupBy('funnel_stage')->pluck('c', 'funnel_stage')->toArray();
        $sensitive = (clone $q)->where('sensitive_word_check_status', 'block')->count();
        $replied = (clone $q)->whereIn('funnel_stage', ['replied', 'crm_pushed'])->count();

        return [
            'total' => (clone $q)->count(),
            'inquiry' => (clone $q)->where('is_inquiry', 1)->count(),
            'realConsumer' => (clone $q)->where('homepage_check_result', 'real_consumer')->count(),
            'marketing' => (clone $q)->where('homepage_check_result', 'marketing')->count(),
            'replied' => $replied,
            'sensitiveBlocked' => $sensitive,
            'stages' => $stages,
        ];
    }

    /**
     * 处理单条采集评论（Worker 回调逐条或批量）
     *
     * @param  array{
     *   name?:string,nickname?:string,user_id?:string,content:string,
     *   avatar?:string,note_url?:string,homepage_url?:string
     * }  $row
     */
    public function processCollectedComment(CrawlerTask $task, array $row): SpiderCommentRecord
    {
        if (!(int) ($task->enable_comment_collect ?? 1)) {
            throw new RuntimeException('任务未开启评论采集');
        }

        $content = trim((string) ($row['content'] ?? ''));
        $name = trim((string) ($row['name'] ?? $row['nickname'] ?? '访客'));
        $userId = trim((string) ($row['user_id'] ?? $row['comment_user_id'] ?? ''));
        $noteUrl = (string) ($row['note_url'] ?? $row['noteUrl'] ?? $task->target ?? '');
        $homepageUrl = (string) ($row['homepage_url'] ?? $row['user_homepage_url'] ?? '');

        $record = SpiderCommentRecord::create([
            'task_id' => $task->id,
            'tenant_id' => $task->tenant_id,
            'note_url' => $noteUrl ?: null,
            'comment_user_name' => $name !== '' ? $name : '访客',
            'comment_user_id' => $userId !== '' ? $userId : null,
            'comment_content' => $content,
            'user_homepage_url' => $homepageUrl ?: null,
            'homepage_check_result' => 'pending',
            'is_inquiry' => false,
            'funnel_stage' => 'collected',
            'operate_social_account' => $task->social_account_id,
            'run_status' => 'processing',
            'create_time' => now(),
        ]);

        PackageQuota::recordProxyRequest((int) $task->tenant_id);

        if ($content === '') {
            return $this->markFailed($record, '评论内容为空', 'filtered');
        }

        // 黑名单
        if ($userId && $this->isBlacklisted((int) $task->tenant_id, $userId)) {
            $record->update([
                'funnel_stage' => 'blacklisted',
                'run_status' => 'skipped',
                'homepage_check_result' => 'marketing',
                'fail_reason' => '营销号黑名单',
            ]);

            return $record->fresh();
        }

        // 第一层：咨询意向筛选（关键词 + 简单广告过滤）
        if ($this->isSpamOrAd($content) || !$this->isInquiry($content)) {
            $record->update([
                'is_inquiry' => false,
                'funnel_stage' => 'filtered',
                'run_status' => 'skipped',
                'homepage_check_result' => 'skipped',
            ]);

            return $record->fresh();
        }
        $record->update(['is_inquiry' => true]);

        // 第二层：主页核验
        if ((int) ($task->enable_user_homepage_check ?? 0) === 1) {
            $check = $this->checkHomepage($task, $record, $row);
            if (($check['result'] ?? '') === 'marketing') {
                $this->addBlacklist((int) $task->tenant_id, $record, $check);
                $record->update([
                    'homepage_check_result' => 'marketing',
                    'homepage_meta' => $check['meta'] ?? [],
                    'funnel_stage' => 'homepage_failed',
                    'run_status' => 'skipped',
                    'fail_reason' => '主页核验判定为营销商家号',
                ]);

                return $record->fresh();
            }
            $record->update([
                'homepage_check_result' => $check['result'] ?? 'unknown',
                'homepage_meta' => $check['meta'] ?? [],
                'user_homepage_url' => $check['homepage_url'] ?? $record->user_homepage_url,
            ]);
        } else {
            $record->update(['homepage_check_result' => 'skipped']);
        }

        // 自动评论回复
        if ((int) ($task->auto_comment_reply ?? 0) === 1) {
            return $this->tryAutoReply($task, $record->fresh());
        }

        $record->update([
            'funnel_stage' => 'ready_reply',
            'run_status' => 'success',
        ]);

        return $record->fresh();
    }

    public function tryAutoReply(CrawlerTask $task, SpiderCommentRecord $record): SpiderCommentRecord
    {
        if ((int) $task->today_reply_count >= (int) ($task->daily_reply_max ?: 30)) {
            return $this->markFailed($record, '已达单日最大回复条数', 'failed');
        }

        $interval = max(30, (int) ($task->reply_interval ?: 90));
        $lockKey = 'comment_reply_gap:'.$task->id;
        if (Cache::has($lockKey)) {
            $record->update([
                'funnel_stage' => 'ready_reply',
                'run_status' => 'pending',
                'fail_reason' => '等待回复间隔',
            ]);

            return $record->fresh();
        }

        // AI 生成真人闲聊口吻（禁止硬广/AI字眼）
        $draft = $this->generateCasualReply((int) $task->tenant_id, (string) $record->comment_content);
        PackageQuota::recordAiCallAndMaybeDisableAutoReply((int) $task->tenant_id);

        $check = SensitiveWordFilter::check($draft, (int) $task->tenant_id);
        $status = 'pass';
        if (!$check['ok']) {
            // 命中屏蔽词：尝试替换后再检，仍失败则跳过
            $draft2 = $this->scrubSensitive($draft, $check['hits']);
            $check2 = SensitiveWordFilter::check($draft2, (int) $task->tenant_id);
            if (!$check2['ok']) {
                SensitiveWordFilter::logAlert(
                    (int) $task->tenant_id,
                    null,
                    null,
                    $check2['hits'],
                    $draft2,
                    'comment_blocked'
                );
                $record->update([
                    'ai_reply_content' => $draft2,
                    'sensitive_word_check_status' => 'block',
                    'hit_sensitive_words' => implode(',', $check2['hits']),
                    'funnel_stage' => 'sensitive_block',
                    'run_status' => 'skipped',
                    'fail_reason' => '敏感词拦截，跳过回复',
                ]);

                return $record->fresh();
            }
            $draft = $draft2;
            $status = 'regenerated';
            $check = $check2;
        } elseif (!empty($check['hits'])) {
            $status = 'warn';
        }

        // 调用 Python 模拟真人发送（失败则保留草稿待重试）
        $send = $this->dispatchPythonReply($task, $record, $draft);
        if (!$send['ok']) {
            $record->update([
                'ai_reply_content' => $draft,
                'sensitive_word_check_status' => $status,
                'hit_sensitive_words' => implode(',', $check['hits'] ?? []),
                'funnel_stage' => 'failed',
                'run_status' => 'failed',
                'fail_reason' => $send['message'] ?? '评论发送失败',
            ]);

            return $record->fresh();
        }

        Cache::put($lockKey, 1, $interval);
        $task->increment('today_reply_count');
        PackageQuota::recordProxyRequest((int) $task->tenant_id);

        $crmId = $this->pushCrmFromRecord($task, $record, $draft);

        $record->update([
            'ai_reply_content' => $draft,
            'sensitive_word_check_status' => $status,
            'hit_sensitive_words' => implode(',', $check['hits'] ?? []),
            'funnel_stage' => $crmId ? 'crm_pushed' : 'replied',
            'run_status' => 'success',
            'crm_lead_id' => $crmId,
            'fail_reason' => null,
        ]);

        return $record->fresh();
    }

    public function isInquiry(string $content): bool
    {
        $text = mb_strtolower(trim($content));
        if ($text === '') {
            return false;
        }
        $keywords = [
            '代理', '怎么买', '多少钱', '价格', '咨询', '合作', '联系', '想要',
            '求购', '微信', '代发', '加盟', '拿货', '详聊', '私聊', '怎么做',
            '有没有货', '怎么加盟', '想了解', '感兴趣', '推荐', '求推荐', '哪家好',
            '好用吗', '值得买吗', '同款', '链接',
        ];
        foreach ($keywords as $kw) {
            if (mb_stripos($text, mb_strtolower($kw)) !== false) {
                return true;
            }
        }

        return false;
    }

    public function isSpamOrAd(string $content): bool
    {
        $text = mb_strtolower($content);
        // 表情灌水：几乎全是符号/emoji
        $plain = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\s\.\!\?~~]+/u', '', $text);
        if ($plain !== null && mb_strlen($plain) < 2 && mb_strlen($text) >= 2) {
            return true;
        }
        $adHints = ['加微信', '加v', '私聊领', '免费领取', '刷单', '日入', '稳赚', '引流粉'];
        foreach ($adHints as $h) {
            if (mb_stripos($text, $h) !== false) {
                return true;
            }
        }

        return false;
    }

    public function isBlacklisted(int $tenantId, string $platformUserId): bool
    {
        return MarketingAccountBlacklist::query()
            ->where('tenant_id', $tenantId)
            ->where('platform', 'xiaohongshu')
            ->where('platform_user_id', $platformUserId)
            ->exists();
    }

    public function listBlacklist(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 20)));
        $q = MarketingAccountBlacklist::query()->with('tenant')->orderByDesc('id');
        if ($scopeTenantId) {
            $q->where('tenant_id', $scopeTenantId);
        } elseif (!empty($filters['tenantId'])) {
            $q->where('tenant_id', (int) $filters['tenantId']);
        }

        return $q->paginate($size, ['*'], 'page', $page);
    }

    private function checkHomepage(CrawlerTask $task, SpiderCommentRecord $record, array $row): array
    {
        // 随机休眠，规避高频主页访问
        usleep(random_int(800_000, 2_500_000));

        $base = rtrim((string) config('services.python_login.base_url', 'http://127.0.0.1:8100'), '/');
        $homepage = $record->user_homepage_url
            ?: (string) ($row['homepage_url'] ?? '');
        $payload = [
            'tenant_id' => (int) $task->tenant_id,
            'task_id' => $task->id,
            'user_id' => $record->comment_user_id,
            'nickname' => $record->comment_user_name,
            'homepage_url' => $homepage,
            'note_count' => $row['note_count'] ?? null,
            'fans_count' => $row['fans_count'] ?? null,
            'recent_posts' => $row['recent_posts'] ?? [],
        ];

        try {
            $resp = Http::timeout(25)->post($base.'/api/check-homepage', $payload);
            if ($resp->successful()) {
                $data = $resp->json('data') ?? $resp->json();
                PackageQuota::recordProxyRequest((int) $task->tenant_id);

                return [
                    'result' => $data['result'] ?? 'unknown',
                    'meta' => $data['meta'] ?? $data,
                    'homepage_url' => $data['homepage_url'] ?? $homepage,
                ];
            }
        } catch (\Throwable) {
            // fallback 本地启发式
        }

        return $this->heuristicHomepageCheck($row, $homepage);
    }

    private function heuristicHomepageCheck(array $row, string $homepage): array
    {
        $notes = (int) ($row['note_count'] ?? $row['notes'] ?? 0);
        $fans = (int) ($row['fans_count'] ?? $row['fans'] ?? 0);
        $posts = $row['recent_posts'] ?? [];
        $bizHits = 0;
        foreach ((array) $posts as $p) {
            $t = is_string($p) ? $p : (string) ($p['title'] ?? $p['content'] ?? '');
            foreach (['代理', '招商', '拿货', '批发', '微商', '招代理'] as $kw) {
                if (mb_stripos($t, $kw) !== false) {
                    $bizHits++;
                }
            }
        }
        $result = 'real_consumer';
        if ($bizHits >= 2 || ($notes > 80 && $fans > 5000) || ($fans > 20000)) {
            $result = 'marketing';
        } elseif ($notes === 0 && $fans === 0 && empty($posts)) {
            $result = 'unknown';
        }

        return [
            'result' => $result,
            'meta' => [
                'note_count' => $notes,
                'fans_count' => $fans,
                'biz_hits' => $bizHits,
                'source' => 'heuristic',
            ],
            'homepage_url' => $homepage,
        ];
    }

    private function addBlacklist(int $tenantId, SpiderCommentRecord $record, array $check): void
    {
        if (!$record->comment_user_id) {
            return;
        }
        MarketingAccountBlacklist::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'platform' => 'xiaohongshu',
                'platform_user_id' => $record->comment_user_id,
            ],
            [
                'nickname' => $record->comment_user_name,
                'homepage_url' => $check['homepage_url'] ?? $record->user_homepage_url,
                'reason' => '主页核验判定营销商家号',
                'meta' => $check['meta'] ?? [],
            ]
        );
    }

    private function generateCasualReply(int $tenantId, string $comment): string
    {
        // 强制生活化口吻：禁用 AI/客服话术字眼
        $promptHint = '你是普通人在小红书评论区闲聊，口语化、短句、像朋友随口一说。'
            .'禁止硬广、禁止留联系方式、禁止出现「AI」「机器人」「客服」「助手」「专员」等字眼。'
            .'针对对方留言给出自然回应，不超过 40 字。对方说：'.$comment;

        try {
            $out = MessageAiReplyEngine::generateCasualCommentReply($tenantId, $promptHint, $comment);
            if (is_string($out) && trim($out) !== '') {
                return $this->stripForbiddenWords(trim($out));
            }
        } catch (\Throwable) {
            // fallback
        }

        $fallbacks = [
            '哈哈同感，我之前也纠结过这个问题～',
            '哇这个我也在关注，求轻点安利哈哈哈',
            '听起来不错诶，我再蹲蹲看效果',
            '懂你，最近也在找类似的，一起交流呀',
        ];

        return $fallbacks[array_rand($fallbacks)];
    }

    private function stripForbiddenWords(string $text): string
    {
        $banned = ['AI', 'ai', '机器人', '客服', '助手', '智能客服', '专员', '在线客服'];
        return trim(str_replace($banned, '', $text));
    }

    private function scrubSensitive(string $text, array $hits): string
    {
        $out = $text;
        foreach ($hits as $w) {
            $out = str_replace($w, '**', $out);
        }

        return $this->stripForbiddenWords($out);
    }

    private function dispatchPythonReply(CrawlerTask $task, SpiderCommentRecord $record, string $reply): array
    {
        $base = rtrim((string) config('services.python_login.base_url', 'http://127.0.0.1:8100'), '/');
        $ctx = $task->resolveRuntimeContext();
        try {
            $resp = Http::timeout(60)->post($base.'/api/reply-comment', [
                'task_id' => $task->id,
                'note_url' => $record->note_url,
                'reply_content' => $reply,
                'reply_interval' => (int) $task->reply_interval,
                'cookies' => $ctx['cookies'] ?? [],
                'proxy_server_addr' => $ctx['proxy_server_addr'] ?? null,
                'user_agent' => $ctx['user_agent'] ?? null,
                'behavior' => $ctx['behavior'] ?? [],
                'dry_run' => (bool) config('services.python_login.comment_dry_run', true),
            ]);
            if ($resp->successful()) {
                $ok = (bool) ($resp->json('data.ok') ?? $resp->json('ok') ?? true);

                return ['ok' => $ok, 'message' => $resp->json('message') ?? $resp->json('data.message')];
            }

            return ['ok' => false, 'message' => 'Python回复服务异常: '.$resp->status()];
        } catch (\Throwable $e) {
            // 本地演示：dry-run 视为成功，便于联调闭环
            if (config('app.env') === 'local' || config('services.python_login.comment_dry_run', true)) {
                return ['ok' => true, 'message' => 'local-dry-run: '.$e->getMessage()];
            }

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function pushCrmFromRecord(CrawlerTask $task, SpiderCommentRecord $record, string $reply): ?int
    {
        try {
            $lead = CrmLead::create([
                'nickname' => $record->comment_user_name,
                'phone' => '',
                'quote' => mb_substr((string) $record->comment_content, 0, 200),
                'channel' => '小红书评论',
                'tenant_id' => $task->tenant_id,
                'score' => 75,
                'intent' => 'high',
                'status' => '待跟进',
                'follower' => '',
                'tags' => ['评论引流', '意向咨询'],
                'remark' => '笔记：'.($record->note_url ?: '-').'；AI回复：'.$reply,
            ]);

            return $lead->id;
        } catch (\Throwable) {
            return null;
        }
    }

    private function markFailed(SpiderCommentRecord $record, string $reason, string $stage): SpiderCommentRecord
    {
        $record->update([
            'funnel_stage' => $stage,
            'run_status' => $stage === 'filtered' || $stage === 'blacklisted' ? 'skipped' : 'failed',
            'fail_reason' => $reason,
        ]);

        return $record->fresh();
    }
}
