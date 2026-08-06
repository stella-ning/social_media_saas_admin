<?php

namespace App\Services;

use App\Models\CrmLead;
use App\Models\Message;
use App\Models\MessageSession;
use App\Models\QuickReply;
use App\Support\MessageAiReplyEngine;
use App\Support\PackageQuota;
use App\Support\SensitiveWordFilter;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * 消息会话服务
 * 完整链路：访客消息入库 →（未人工接管）AI 真人话术回复 → 意向打标 → 高意向可推 CRM
 */
class MessageService
{
    /** 闲置超时小时数（可后续做成配置） */
    public const IDLE_HOURS = 48;

    public function sessions(array $filters, ?int $scopeTenantId = null): Collection
    {
        $query = MessageSession::query()
            ->with(['tenant', 'socialAccount'])
            ->orderByDesc('updated_at');

        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        }

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")->orWhere('last_msg', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['handle_status']) || !empty($filters['handleStatus'])) {
            $query->where('handle_status', $filters['handle_status'] ?? $filters['handleStatus']);
        }
        if (!empty($filters['session_status']) || !empty($filters['sessionStatus'])) {
            $query->where('session_status', $filters['session_status'] ?? $filters['sessionStatus']);
        }
        if (!empty($filters['intent_level']) || !empty($filters['intentLevel'])) {
            $query->where('intent_level', $filters['intent_level'] ?? $filters['intentLevel']);
        }

        return $query->get()->map->toFrontendArray();
    }

    public function detail(MessageSession $session): array
    {
        $session->load(['tenant', 'socialAccount', 'messages']);
        // 打开会话：未读清零，标记已读
        $session->update([
            'unread' => 0,
            'handle_status' => $session->handle_status === MessageSession::HANDLE_PROCESSED
                ? MessageSession::HANDLE_PROCESSED
                : MessageSession::HANDLE_READ,
        ]);
        Message::query()
            ->where('session_id', $session->id)
            ->where('from_type', 'user')
            ->where('read_status', 'unread')
            ->update(['read_status' => 'read']);

        return $session->fresh(['tenant', 'socialAccount', 'messages'])->toFrontendArray();
    }

    /**
     * 发送消息
     * - from=user：访客消息，触发 AI 自动接待（未人工接管时）
     * - from=human：客服人工发送，不触发 AI
     */
    public function send(MessageSession $session, string $content, string $from = 'human'): array
    {
        if ($session->session_status === 'closed') {
            throw new RuntimeException('会话已关闭，请重新开启后再发送');
        }

        // 人工发送也过敏感词
        if ($from === 'human' || $from === 'ai') {
            $check = SensitiveWordFilter::check($content, (int) $session->tenant_id);
            if (!$check['ok']) {
                SensitiveWordFilter::logAlert(
                    (int) $session->tenant_id,
                    $session->id,
                    null,
                    $check['hits'],
                    $content,
                    'blocked'
                );
                throw new RuntimeException('消息含敏感词（'.implode('、', $check['hits']).'），已拦截');
            }
        }

        $msg = Message::create([
            'session_id' => $session->id,
            'from_type' => $from,
            'content' => $content,
            'read_status' => $from === 'user' ? 'unread' : 'read',
        ]);

        $payload = [
            'last_msg' => mb_substr($content, 0, 80),
            'time_label' => '刚刚',
            'date_label' => now()->format('Y-m-d H:i'),
            'session_status' => 'open',
            'closed_at' => null,
        ];

        if ($from === 'user') {
            $payload['unread'] = (int) $session->unread + 1;
            $payload['handle_status'] = MessageSession::HANDLE_UNREAD;
            $payload['last_visitor_at'] = now();
        }

        $session->update($payload);

        // 访客消息 + 开启 AI + 未人工接管 → 自动回复
        if ($from === 'user' && $session->ai_auto_reply && !(int) $session->human_takeover) {
            try {
                $this->autoReply($session->fresh(), $content);
            } catch (RuntimeException $e) {
                // AI 失败不阻断访客消息入库，写一条系统提示给客服侧
                Message::create([
                    'session_id' => $session->id,
                    'from_type' => 'ai',
                    'content' => '【系统提示】自动回复暂不可用：'.$e->getMessage(),
                    'read_status' => 'read',
                    'is_blocked' => 1,
                    'block_reason' => $e->getMessage(),
                ]);
            }
        }

        return $this->detail($session->fresh());
    }

    /** AI 自动接待 */
    public function autoReply(MessageSession $session, string $visitorMsg): Message
    {
        if ((int) $session->human_takeover === 1 || !$session->ai_auto_reply) {
            throw new RuntimeException('当前会话已转人工或未开启自动回复');
        }

        $result = MessageAiReplyEngine::generate($session, $visitorMsg);

        $aiMsg = Message::create([
            'session_id' => $session->id,
            'from_type' => 'ai',
            'content' => $result['content'],
            'read_status' => 'read',
        ]);

        $tags = $session->tags ?: [];
        $intentLabel = MessageSession::intentLabel($result['intent']);
        $tags = array_values(array_unique(array_merge($tags, [$intentLabel])));
        if ($result['consult_product']) {
            $tags[] = $result['consult_product'];
        }
        if ($session->platform) {
            $tags[] = $session->platform;
        }

        $session->update([
            'last_msg' => mb_substr($result['content'], 0, 80),
            'time_label' => '刚刚',
            'intent_level' => $result['intent'],
            'consult_product' => $result['consult_product'] ?: $session->consult_product,
            'tags' => array_values(array_unique($tags)),
        ]);

        return $aiMsg;
    }

    public function updateSettings(MessageSession $session, array $data): MessageSession
    {
        $payload = [];
        if (array_key_exists('aiAutoReply', $data) || array_key_exists('ai_auto_reply', $data)) {
            $payload['ai_auto_reply'] = !empty($data['aiAutoReply'] ?? $data['ai_auto_reply']);
        }
        if (array_key_exists('humanTakeover', $data) || array_key_exists('human_takeover', $data)) {
            $takeover = !empty($data['humanTakeover'] ?? $data['human_takeover']);
            $payload['human_takeover'] = $takeover ? 1 : 0;
            if ($takeover) {
                // 转人工：强制关闭 AI 自动回复
                $payload['ai_auto_reply'] = false;
            }
        }
        if (array_key_exists('intentLevel', $data) || array_key_exists('intent_level', $data)) {
            $level = $data['intentLevel'] ?? $data['intent_level'];
            if (in_array($level, ['none', 'normal', 'high'], true)) {
                $payload['intent_level'] = $level;
            }
        }
        if (array_key_exists('consultProduct', $data) || array_key_exists('consult_product', $data)) {
            $payload['consult_product'] = $data['consultProduct'] ?? $data['consult_product'];
        }
        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $payload['tags'] = array_values($data['tags']);
        }
        if (array_key_exists('handleStatus', $data) || array_key_exists('handle_status', $data)) {
            $hs = $data['handleStatus'] ?? $data['handle_status'];
            if (in_array($hs, ['unread', 'read', 'processed'], true)) {
                $payload['handle_status'] = $hs;
            }
        }
        if (array_key_exists('sessionStatus', $data) || array_key_exists('session_status', $data)) {
            $ss = $data['sessionStatus'] ?? $data['session_status'];
            if (in_array($ss, ['open', 'closed'], true)) {
                $payload['session_status'] = $ss;
                $payload['closed_at'] = $ss === 'closed' ? now() : null;
            }
        }

        if ($payload) {
            $session->update($payload);
        }

        return $session->fresh(['tenant', 'socialAccount']);
    }

    /** 高意向一键推送 CRM */
    public function pushToCrm(MessageSession $session): CrmLead
    {
        if ($session->crm_lead_id) {
            $exist = CrmLead::query()->find($session->crm_lead_id);
            if ($exist) {
                return $exist;
            }
        }

        $intent = $session->intent_level ?: 'none';
        $score = match ($intent) {
            'high' => 90,
            'normal' => 70,
            default => 40,
        };

        $lead = CrmLead::create([
            'nickname' => $session->name,
            'phone' => '',
            'quote' => $session->last_msg ?: '来自消息会话',
            'channel' => $session->platform ?: '私信',
            'tenant_id' => $session->tenant_id,
            'score' => $score,
            'intent' => $intent === 'high' ? 'high' : ($intent === 'normal' ? 'mid' : 'low'),
            'status' => '未处理',
            'follower' => '',
            'tags' => array_values(array_unique(array_filter([
                MessageSession::intentLabel($intent),
                $session->consult_product,
                $session->platform,
                '消息会话推送',
            ]))),
            'remark' => 'session_id='.$session->id,
        ]);

        $session->update([
            'crm_lead_id' => $lead->id,
            'handle_status' => MessageSession::HANDLE_PROCESSED,
            'intent_level' => $intent === 'none' ? 'high' : $intent,
        ]);

        return $lead;
    }

    public function quickReplies(int $tenantId): array
    {
        return QuickReply::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map->toFrontendArray()
            ->values()
            ->all();
    }

    public function saveQuickReply(array $data): QuickReply
    {
        $id = $data['id'] ?? null;
        $payload = [
            'tenant_id' => (int) ($data['tenant_id'] ?? $data['tenantId']),
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'] ?? '常用',
            'sort' => (int) ($data['sort'] ?? 0),
        ];
        if ($id) {
            $row = QuickReply::query()->findOrFail($id);
            $row->update($payload);

            return $row->fresh();
        }

        return QuickReply::create($payload);
    }

    public function deleteQuickReply(QuickReply $reply): void
    {
        $reply->delete();
    }

    /** 闲置超时关闭会话 */
    public function closeIdleSessions(int $hours = self::IDLE_HOURS): int
    {
        $before = now()->subHours($hours);
        $sessions = MessageSession::query()
            ->where('session_status', 'open')
            ->where(function ($q) use ($before) {
                $q->where('last_visitor_at', '<', $before)
                    ->orWhere(function ($q2) use ($before) {
                        $q2->whereNull('last_visitor_at')->where('updated_at', '<', $before);
                    });
            })
            ->get();

        foreach ($sessions as $s) {
            $s->update([
                'session_status' => 'closed',
                'closed_at' => now(),
                'ai_auto_reply' => false,
            ]);
            Message::create([
                'session_id' => $s->id,
                'from_type' => 'ai',
                'content' => '【系统】会话长时间无新消息，已自动关闭。',
                'read_status' => 'read',
            ]);
        }

        return $sessions->count();
    }

    /** 模拟接收访客私信/评论（供演示与爬虫回调） */
    public function ingestVisitorMessage(array $data): MessageSession
    {
        $tenantId = (int) ($data['tenant_id'] ?? $data['tenantId']);
        PackageQuota::settingForTenant($tenantId); // ensure exists
        MessageAiReplyEngine::assertPlatformAllowed($tenantId, (string) ($data['platform'] ?? '小红书'));

        $session = null;
        if (!empty($data['session_id'])) {
            $session = MessageSession::query()->find($data['session_id']);
        }
        if (!$session) {
            $session = MessageSession::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => $data['name'] ?? '访客',
                    'platform' => $data['platform'] ?? '小红书',
                    'session_status' => 'open',
                ],
                [
                    'avatar' => $data['avatar'] ?? 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
                    'social_account_id' => $data['social_account_id'] ?? $data['socialAccountId'] ?? null,
                    'ai_auto_reply' => true,
                    'human_takeover' => 0,
                    'handle_status' => MessageSession::HANDLE_UNREAD,
                    'intent_level' => 'none',
                    'date_label' => now()->format('Y-m-d H:i'),
                ]
            );
        }

        // 已关闭会话收到新消息则重新打开
        if ($session->session_status === 'closed') {
            $session->update([
                'session_status' => 'open',
                'closed_at' => null,
                'ai_auto_reply' => !(int) $session->human_takeover,
            ]);
        }

        $this->send($session->fresh(), (string) $data['content'], 'user');

        return $session->fresh(['messages', 'tenant', 'socialAccount']);
    }
}
