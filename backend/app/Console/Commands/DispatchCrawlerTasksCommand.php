<?php

namespace App\Console\Commands;

use App\Models\CrawlerTask;
use App\Support\CrawlerCommentBridge;
use App\Support\PackageQuota;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 爬虫调度：拉取 running 任务，组装账号 Cookie + 专属代理载荷
 * 实际抓取可由外部 Worker 消费；本命令负责调度侧取数与配额记账
 *
 * php artisan crawler:dispatch
 * php artisan crawler:dispatch --dry-run
 * php artisan crawler:dispatch --ingest-demo  （调度后模拟评论采集并接入会话）
 */
class DispatchCrawlerTasksCommand extends Command
{
    protected $signature = 'crawler:dispatch
        {--limit=50 : 单次最多调度任务数}
        {--dry-run : 只输出载荷摘要，不记账}
        {--ingest-demo : 调度成功后模拟采集评论区并接入咨询留言会话}';

    protected $description = '调度运行中的爬虫任务：读取绑定账号 Cookie 与专属代理 IP';

    public function handle(CrawlerCommentBridge $bridge): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');
        $ingestDemo = (bool) $this->option('ingest-demo');

        $tasks = CrawlerTask::query()
            ->with(['socialAccount.proxy', 'socialAccount.activeCookie', 'tenant'])
            ->where('status', 'running')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('暂无运行中的爬虫任务');

            return self::SUCCESS;
        }

        $ok = 0;
        $skip = 0;
        foreach ($tasks as $task) {
            $tenantId = (int) $task->tenant_id;
            try {
                PackageQuota::assertDailyProxyRequestAvailable($tenantId);
            } catch (\Throwable $e) {
                $this->warn("任务#{$task->id} 跳过：".$e->getMessage());
                $skip++;
                continue;
            }

            if (!$task->social_account_id) {
                $this->warn("任务#{$task->id} 未绑定社媒账号，跳过");
                $skip++;
                continue;
            }

            $ctx = $task->resolveRuntimeContext();
            if (empty($ctx['proxy_server_addr'])) {
                $this->warn("任务#{$task->id} 账号缺少专属代理，跳过");
                $skip++;
                continue;
            }
            if (empty($ctx['cookies'])) {
                $this->warn("任务#{$task->id} 账号 Cookie 不可用，跳过");
                $skip++;
                continue;
            }

            // 调度摘要：禁止输出 cookie / 密钥明文
            $brief = [
                'task_id' => $ctx['task_id'],
                'social_account_id' => $ctx['social_account_id'],
                'platform' => $ctx['platform'],
                'proxy_ip_id' => $ctx['proxy_ip_id'],
                'has_cookie' => true,
                'keywords' => $ctx['keywords'],
            ];
            $this->line('dispatch '.json_encode($brief, JSON_UNESCAPED_UNICODE));

            if (!$dry) {
                PackageQuota::recordProxySuccessAndMaybePause($tenantId);
                Log::info('crawler.dispatch', $brief);

                // 演示闭环：调度 → 模拟采集评论区 → 咨询留言进会话
                if ($ingestDemo) {
                    try {
                        $result = $bridge->simulateCollect($task);
                        $this->info("  → 接入咨询留言 {$result['inquiry']} 条（会话 ".count($result['sessions']).'）');
                    } catch (\Throwable $e) {
                        $this->warn("  → 接入留言失败：".$e->getMessage());
                    }
                }
            }
            $ok++;
        }

        $this->info("调度完成：成功 {$ok}，跳过 {$skip}");

        return self::SUCCESS;
    }
}
