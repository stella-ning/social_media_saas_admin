<?php

namespace App\Services;

use App\Models\CrawlerTask;
use App\Models\CrawlerTaskLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CrawlerTaskService
{
    public function list(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 10)));

        $query = CrawlerTask::query()->with('tenant')->orderByDesc('id');

        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        } elseif (!empty($filters['tenant'])) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', $filters['tenant']));
        }

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")->orWhere('target', 'like', "%{$kw}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($size, ['*'], 'page', $page);
    }

    public function create(array $data): CrawlerTask
    {
        $tenantId = (int) ($data['tenantId'] ?? $data['tenant_id']);
        \App\Support\PackageQuota::assertCanCreateCrawlerTask($tenantId);

        $taskType = $data['taskType'] ?? $data['task_type'] ?? 'keyword';
        $keywords = $data['keywords'] ?? '';
        $target = $taskType === 'keyword'
            ? '关键词：'.$keywords
            : '监控：'.$keywords;

        $task = CrawlerTask::create([
            'name' => $data['name'],
            'platform' => $data['platform'],
            'task_type' => $taskType,
            'keywords' => $keywords,
            'target' => $target,
            'tenant_id' => $tenantId,
            'social_account_id' => $data['socialAccountId'] ?? $data['social_account_id'] ?? null,
            'frequency' => $data['frequency'] ?? '每2小时',
            'status' => 'running',
            'today_count' => 0,
            'daily_limit' => $data['dailyLimit'] ?? $data['daily_limit'] ?? 500,
        ]);

        // 硬性约束：任务若绑定了社媒账号，运行必须使用其专属代理（不可更换）
        // 同时按三层优先级解析 AI 配置，供爬虫 Worker 下发大模型
        if ($task->social_account_id) {
            $proxy = $task->resolveBoundProxy();
            $account = $task->socialAccount;
            $aiBrief = null;
            if ($account) {
                $resolved = \App\Support\AiConfigResolver::resolveForAccount($account);
                $aiBrief = [
                    'source' => $resolved['source'],
                    'model' => $resolved['params']['ai_model'] ?? null,
                    'prompt' => $resolved['prompt']['name'] ?? null,
                    'knowledge_count' => count($resolved['knowledge_docs']),
                ];
                // 销毁可能含明文 key 的结构
                unset($resolved);
            }
            CrawlerTaskLog::create([
                'task_id' => $task->id,
                'type' => $proxy ? 'success' : 'warning',
                'content' => $proxy
                    ? '任务创建并启动，强制代理：'.$proxy->address
                        .($aiBrief ? '；AI来源='.$aiBrief['source'].' 模型='.($aiBrief['model'] ?? '-') : '')
                    : '任务已创建，但绑定账号缺少专属代理 IP',
                'logged_at' => now(),
            ]);
        } else {
            CrawlerTaskLog::create([
                'task_id' => $task->id,
                'type' => 'success',
                'content' => '任务创建并启动',
                'logged_at' => now(),
            ]);
        }

        return $task->load('tenant');
    }

    public function update(CrawlerTask $task, array $data): CrawlerTask
    {
        $payload = [];
        foreach (['name', 'platform', 'frequency'] as $f) {
            if (isset($data[$f])) {
                $payload[$f] = $data[$f];
            }
        }
        if (isset($data['keywords'])) {
            $payload['keywords'] = $data['keywords'];
            $type = $data['taskType'] ?? $task->task_type;
            $payload['target'] = ($type === 'keyword' ? '关键词：' : '监控：').$data['keywords'];
        }
        if (isset($data['dailyLimit']) || isset($data['daily_limit'])) {
            $payload['daily_limit'] = $data['dailyLimit'] ?? $data['daily_limit'];
        }
        $task->update($payload);
        return $task->fresh('tenant');
    }

    public function toggle(CrawlerTask $task): CrawlerTask
    {
        $next = $task->status === 'running' ? 'paused' : 'running';
        $task->update(['status' => $next]);
        CrawlerTaskLog::create([
            'task_id' => $task->id,
            'type' => $next === 'running' ? 'success' : 'warning',
            'content' => $next === 'running' ? '任务已启动' : '任务已停止',
            'logged_at' => now(),
        ]);
        return $task;
    }

    public function logs(CrawlerTask $task): array
    {
        return $task->logs()->limit(50)->get()->map->toFrontendArray()->values()->all();
    }
}
