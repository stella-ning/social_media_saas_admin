<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageSession;
use Illuminate\Support\Collection;

class MessageService
{
    public function sessions(array $filters, ?int $scopeTenantId = null): Collection
    {
        $query = MessageSession::query()->with('tenant')->orderByDesc('updated_at');

        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        }

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")->orWhere('last_msg', 'like', "%{$kw}%");
            });
        }

        return $query->get()->map->toFrontendArray();
    }

    public function detail(MessageSession $session): array
    {
        $session->load(['tenant', 'messages']);
        $session->update(['unread' => 0]);
        return $session->toFrontendArray();
    }

    public function send(MessageSession $session, string $content, string $from = 'human'): array
    {
        Message::create([
            'session_id' => $session->id,
            'from_type' => $from,
            'content' => $content,
        ]);

        $session->update([
            'last_msg' => mb_substr($content, 0, 80),
            'time_label' => '刚刚',
        ]);

        // AI 自动回复模拟
        if ($session->ai_auto_reply && $from === 'human') {
            Message::create([
                'session_id' => $session->id,
                'from_type' => 'ai',
                'content' => '好的，已收到您的消息。请问还有其他想了解的吗？我可以继续为您介绍政策与货源。',
            ]);
            $session->update(['last_msg' => '好的，已收到您的消息...']);
        }

        return $this->detail($session->fresh());
    }

    public function updateAiSwitch(MessageSession $session, bool $enabled): MessageSession
    {
        $session->update(['ai_auto_reply' => $enabled]);
        return $session;
    }
}
