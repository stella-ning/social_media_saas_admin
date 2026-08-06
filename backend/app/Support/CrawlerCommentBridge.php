<?php

namespace App\Support;

use App\Models\CrawlerTask;
use App\Models\CrawlerTaskLog;
use App\Services\CommentFunnelService;
use App\Services\MessageService;
use RuntimeException;

/**
 * 爬虫采集 → 评论引流漏斗 →（可选）消息会话
 */
class CrawlerCommentBridge
{
    public function __construct(
        private MessageService $messages,
        private CommentFunnelService $funnel
    ) {
    }

    public static function isInquiry(string $content): bool
    {
        return app(CommentFunnelService::class)->isInquiry($content);
    }

    /**
     * @param  array<int, array>  $comments
     * @return array{collected:int,inquiry:int,skipped:int,replied:int,records:array,sessions:array,task:array}
     */
    public function handleCollectCallback(CrawlerTask $task, array $comments, bool $allowPaused = false): array
    {
        if (!$allowPaused && $task->status !== 'running') {
            throw new RuntimeException('任务未运行，无法接入采集结果（请先启动任务）');
        }
        if (!$task->social_account_id) {
            throw new RuntimeException('任务未绑定执行社媒账号，无法关联会话');
        }
        if (!(int) ($task->enable_comment_collect ?? 1)) {
            throw new RuntimeException('任务未开启「采集评论」开关');
        }

        // 基础版仅小红书
        $setting = \App\Support\PackageQuota::settingForTenant((int) $task->tenant_id);
        $allowed = $setting->allow_platforms ?: ['xiaohongshu'];
        if (!in_array('xiaohongshu', $allowed, true) && $task->platform === '小红书') {
            throw new RuntimeException('当前套餐不支持小红书爬虫，请升级套餐');
        }

        $collected = count($comments);
        $inquiry = 0;
        $skipped = 0;
        $replied = 0;
        $records = [];
        $sessions = [];

        foreach ($comments as $row) {
            try {
                $record = $this->funnel->processCollectedComment($task->fresh(), $row);
                $records[] = $record->toFrontendArray();
                if ($record->is_inquiry) {
                    $inquiry++;
                } else {
                    $skipped++;
                }
                if (in_array($record->funnel_stage, ['replied', 'crm_pushed'], true)) {
                    $replied++;
                }

                // 合格意向且未自动回复时，仍可接入消息会话（人工/AI 私信接待）
                if (
                    $record->is_inquiry
                    && in_array($record->homepage_check_result, ['real_consumer', 'skipped', 'unknown', null], true)
                    && !(int) ($task->auto_comment_reply ?? 0)
                ) {
                    $platformLabel = $task->platform ?: '小红书';
                    $session = $this->messages->ingestVisitorMessage([
                        'tenant_id' => (int) $task->tenant_id,
                        'name' => $record->comment_user_name ?: '访客',
                        'platform' => $platformLabel,
                        'content' => (string) $record->comment_content,
                        'social_account_id' => (int) $task->social_account_id,
                        'avatar' => $row['avatar'] ?? null,
                    ]);
                    $record->update(['message_session_id' => $session->id]);
                    $sessions[] = [
                        'sessionId' => $session->id,
                        'visitor' => $session->name,
                        'intentLevel' => $session->intent_level,
                    ];
                }
            } catch (\Throwable $e) {
                $skipped++;
                CrawlerTaskLog::create([
                    'task_id' => $task->id,
                    'type' => 'warning',
                    'content' => '评论处理失败：'.$e->getMessage(),
                    'logged_at' => now(),
                ]);
            }
        }

        $task->increment('today_count', max(0, $collected));

        CrawlerTaskLog::create([
            'task_id' => $task->id,
            'type' => $inquiry > 0 ? 'success' : 'info',
            'content' => sprintf(
                '评论引流完成：采集 %d，意向 %d，回复 %d，跳过 %d',
                $collected,
                $inquiry,
                $replied,
                $skipped
            ),
            'logged_at' => now(),
        ]);

        return [
            'collected' => $collected,
            'inquiry' => $inquiry,
            'skipped' => $skipped,
            'replied' => $replied,
            'records' => $records,
            'sessions' => $sessions,
            'task' => $task->fresh()->toFrontendArray(),
        ];
    }

    /**
     * 演示：模拟同行评论区采集
     */
    public function simulateCollect(CrawlerTask $task): array
    {
        $demo = [
            [
                'name' => '小鹿吃沙拉',
                'user_id' => 'xhs_u_1001',
                'content' => '求推荐同款，多少钱可以代理呀？',
                'note_url' => 'https://www.xiaohongshu.com/explore/demo1',
                'homepage_url' => 'https://www.xiaohongshu.com/user/profile/demo1',
                'note_count' => 12,
                'fans_count' => 86,
                'recent_posts' => ['今日穿搭', '周末探店'],
            ],
            [
                'name' => '广告号小王',
                'user_id' => 'xhs_u_ads',
                'content' => '加微信免费领取刷单日入过万稳赚！！！',
                'note_url' => 'https://www.xiaohongshu.com/explore/demo1',
                'note_count' => 200,
                'fans_count' => 50000,
                'recent_posts' => ['招代理', '招商加盟', '批发拿货'],
            ],
            [
                'name' => '表情包女孩',
                'user_id' => 'xhs_u_emoji',
                'content' => '😂😂😂👍👍',
                'note_url' => 'https://www.xiaohongshu.com/explore/demo1',
            ],
            [
                'name' => '想护肤的阿哲',
                'user_id' => 'xhs_u_2002',
                'content' => '好用吗？有没有平价替代求推荐',
                'note_url' => 'https://www.xiaohongshu.com/explore/demo2',
                'note_count' => 5,
                'fans_count' => 40,
                'recent_posts' => ['护肤日记'],
            ],
            [
                'name' => '微商大户',
                'user_id' => 'xhs_u_biz',
                'content' => '想了解怎么加盟拿货',
                'note_url' => 'https://www.xiaohongshu.com/explore/demo3',
                'note_count' => 120,
                'fans_count' => 8000,
                'recent_posts' => ['招代理火热', '源头批发', '日赚过千'],
            ],
        ];

        return $this->handleCollectCallback($task, $demo, true);
    }
}
