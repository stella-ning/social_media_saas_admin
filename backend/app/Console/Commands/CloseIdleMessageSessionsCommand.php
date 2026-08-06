<?php

namespace App\Console\Commands;

use App\Services\MessageService;
use Illuminate\Console\Command;

/**
 * 会话闲置超时自动关闭
 * php artisan messages:close-idle --hours=48
 */
class CloseIdleMessageSessionsCommand extends Command
{
    protected $signature = 'messages:close-idle {--hours=48 : 无新消息小时数}';

    protected $description = '长时间无新消息的会话标记为已关闭，并停止 AI 自动回复';

    public function handle(MessageService $service): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $n = $service->closeIdleSessions($hours);
        $this->info("已关闭闲置会话 {$n} 个（阈值 {$hours} 小时）");

        return self::SUCCESS;
    }
}
