<?php

namespace App\Support;

use App\Models\MessageAlertLog;
use App\Models\SensitiveWord;

/**
 * 敏感词过滤：AI 生成内容发送前强制筛查
 */
class SensitiveWordFilter
{
    /**
     * @return array{ok:bool,hits:string[],level:?string}
     */
    public static function check(string $content, ?int $tenantId = null): array
    {
        $words = SensitiveWord::query()
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id');
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })
            ->get(['word', 'level']);

        $hits = [];
        $level = null;
        foreach ($words as $row) {
            if ($row->word !== '' && mb_stripos($content, $row->word) !== false) {
                $hits[] = $row->word;
                if ($row->level === 'block' || $level === null) {
                    $level = $row->level;
                }
            }
        }

        return [
            'ok' => empty($hits) || $level !== 'block',
            'hits' => array_values(array_unique($hits)),
            'level' => $level,
        ];
    }

    public static function logAlert(
        int $tenantId,
        ?int $sessionId,
        ?int $messageId,
        array $hits,
        string $content,
        string $action = 'blocked'
    ): void {
        MessageAlertLog::create([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'message_id' => $messageId,
            'hit_words' => implode(',', $hits),
            'content_snippet' => mb_substr($content, 0, 120),
            'action' => $action,
            'logged_at' => now(),
        ]);
    }
}
