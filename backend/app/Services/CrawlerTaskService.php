<?php

namespace App\Services;

use App\Models\CrawlerTask;
use App\Models\CrawlerTaskLog;
use App\Models\SocialAccount;
use App\Support\PackageQuota;
use App\Support\PlatformEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

/**
 * 爬虫任务服务
 * - 创建前：任务上限 / 账号可用 / 代理日配额全套校验
 * - 运行强制绑定账号专属代理 + Cookie
 */
class CrawlerTaskService
{
    public function list(array $filters, ?int $scopeTenantId = null): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 10)));

        $query = CrawlerTask::query()
            ->with(['tenant', 'socialAccount.proxy'])
            ->orderByDesc('id');

        if ($scopeTenantId) {
            $query->where('tenant_id', $scopeTenantId);
        } elseif (!empty($filters['tenant_id']) || !empty($filters['tenantId'])) {
            $query->where('tenant_id', (int) ($filters['tenant_id'] ?? $filters['tenantId']));
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

    /**
     * 可执行社媒账号：状态正常 + 套餐允许平台 + 已绑定代理
     *
     * @return array{list:array,allowPlatforms:array,maxCrawlerTask:?int}
     */
    public function executableAccounts(int $tenantId): array
    {
        $setting = PackageQuota::settingForTenant($tenantId);
        $allowed = $setting->allow_platforms ?: ['xiaohongshu'];
        $platformCodes = [];
        foreach ($allowed as $key) {
            try {
                $platformCodes[] = PlatformEnum::toCode(
                    match ($key) {
                        'xiaohongshu', 'xhs' => '小红书',
                        'douyin' => '抖音',
                        'channels' => '视频号',
                        default => $key,
                    }
                );
            } catch (\Throwable) {
                // skip unknown
            }
        }
        if (!$platformCodes) {
            $platformCodes = [PlatformEnum::XHS];
        }

        $list = SocialAccount::query()
            ->with('proxy')
            ->where('tenant_id', $tenantId)
            ->where('account_status', 1)
            ->whereIn('platform', $platformCodes)
            ->orderByDesc('id')
            ->get()
            ->map(function (SocialAccount $a) {
                $arr = $a->toFrontendArray();
                $arr['hasProxy'] = (bool) $a->bind_proxy_id;
                $arr['hasCookie'] = $a->activeCookie()->exists();

                return $arr;
            })
            ->values()
            ->all();

        return [
            'list' => $list,
            'allowPlatforms' => $allowed,
            'maxCrawlerTask' => $setting->max_crawler_task,
        ];
    }

    public function create(array $data): CrawlerTask
    {
        $tenantId = (int) ($data['tenantId'] ?? $data['tenant_id']);
        $accountId = (int) ($data['socialAccountId'] ?? $data['social_account_id'] ?? 0);
        if ($accountId <= 0) {
            throw new RuntimeException('请选择执行社媒账号');
        }

        // ① 爬虫任务数量上限 + 套餐有效期
        PackageQuota::assertCanCreateCrawlerTask($tenantId);

        // ② 账号可用 + 归属 + 套餐平台
        $account = $this->assertExecutableAccount($tenantId, $accountId);

        // ③ 代理日配额
        PackageQuota::assertDailyProxyRequestAvailable($tenantId);

        // ④ 平台公共代理自动分配（禁止自有 IP）
        \App\Support\PlatformProxyAllocator::ensureAccountProxy($account);
        $account->refresh();

        $taskType = $data['taskType'] ?? $data['task_type'] ?? 'keyword';
        $keywords = $data['keywords'] ?? '';
        $target = $taskType === 'keyword'
            ? '关键词：'.$keywords
            : '监控：'.$keywords;

        // 平台以账号为准，保证关联一致
        $platformLabel = PlatformEnum::toLabel((int) $account->platform);

        $task = CrawlerTask::create([
            'name' => $data['name'],
            'platform' => $data['platform'] ?? $platformLabel,
            'task_type' => $taskType,
            'keywords' => $keywords,
            'target' => $target,
            'tenant_id' => $tenantId,
            'social_account_id' => $account->id,
            'frequency' => $data['frequency'] ?? '每2小时',
            'status' => 'running',
            'today_count' => 0,
            'daily_limit' => $data['dailyLimit'] ?? $data['daily_limit'] ?? 500,
            'enable_comment_collect' => !empty($data['enableCommentCollect'] ?? $data['enable_comment_collect'] ?? true) ? 1 : 0,
            'enable_user_homepage_check' => !empty($data['enableUserHomepageCheck'] ?? $data['enable_user_homepage_check'] ?? false) ? 1 : 0,
            'auto_comment_reply' => !empty($data['autoCommentReply'] ?? $data['auto_comment_reply'] ?? false) ? 1 : 0,
            'reply_interval' => (int) ($data['replyInterval'] ?? $data['reply_interval'] ?? 90),
            'daily_reply_max' => (int) ($data['dailyReplyMax'] ?? $data['daily_reply_max'] ?? 30),
            'today_reply_count' => 0,
        ]);

        $proxy = $task->resolveBoundProxy();
        $aiBrief = null;
        $resolved = \App\Support\AiConfigResolver::resolveForAccount($account);
        $aiBrief = [
            'source' => $resolved['source'],
            'model' => $resolved['params']['ai_model'] ?? null,
            'prompt' => $resolved['prompt']['name'] ?? null,
            'knowledge_count' => count($resolved['knowledge_docs']),
        ];
        unset($resolved);

        CrawlerTaskLog::create([
            'task_id' => $task->id,
            'type' => $proxy ? 'success' : 'warning',
            'content' => $proxy
                ? '任务创建并启动，执行账号='.$account->account_name
                    .'，强制代理：'.$proxy->address
                    .($aiBrief ? '；AI来源='.$aiBrief['source'].' 模型='.($aiBrief['model'] ?? '-') : '')
                : '任务已创建，但绑定账号缺少专属代理 IP',
            'logged_at' => now(),
        ]);

        if ($proxy) {
            PackageQuota::recordProxySuccessAndMaybePause($tenantId);
        }

        return $task->load(['tenant', 'socialAccount.proxy']);
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
        $boolMap = [
            'enableCommentCollect' => 'enable_comment_collect',
            'enable_comment_collect' => 'enable_comment_collect',
            'enableUserHomepageCheck' => 'enable_user_homepage_check',
            'enable_user_homepage_check' => 'enable_user_homepage_check',
            'autoCommentReply' => 'auto_comment_reply',
            'auto_comment_reply' => 'auto_comment_reply',
        ];
        foreach ($boolMap as $in => $col) {
            if (array_key_exists($in, $data)) {
                $payload[$col] = !empty($data[$in]) ? 1 : 0;
            }
        }
        if (isset($data['replyInterval']) || isset($data['reply_interval'])) {
            $payload['reply_interval'] = (int) ($data['replyInterval'] ?? $data['reply_interval']);
        }
        if (isset($data['dailyReplyMax']) || isset($data['daily_reply_max'])) {
            $payload['daily_reply_max'] = (int) ($data['dailyReplyMax'] ?? $data['daily_reply_max']);
        }
        if (isset($data['socialAccountId']) || isset($data['social_account_id'])) {
            $accountId = (int) ($data['socialAccountId'] ?? $data['social_account_id']);
            $this->assertExecutableAccount((int) $task->tenant_id, $accountId);
            $payload['social_account_id'] = $accountId;
        }
        $task->update($payload);

        return $task->fresh(['tenant', 'socialAccount.proxy']);
    }

    public function toggle(CrawlerTask $task): CrawlerTask
    {
        $next = $task->status === 'running' ? 'paused' : 'running';
        if ($next === 'running') {
            PackageQuota::assertPackageActive((int) $task->tenant_id);
            PackageQuota::assertCanResumeCrawlerTask((int) $task->tenant_id);
            if ($task->social_account_id) {
                $this->assertExecutableAccount((int) $task->tenant_id, (int) $task->social_account_id);
            }
            PackageQuota::assertDailyProxyRequestAvailable((int) $task->tenant_id);
            // 启动时从平台公共代理池自动分配
            \App\Support\PlatformProxyAllocator::ensureTaskProxy($task->fresh(['socialAccount']));
        }
        $task->update(['status' => $next]);
        CrawlerTaskLog::create([
            'task_id' => $task->id,
            'type' => $next === 'running' ? 'success' : 'warning',
            'content' => $next === 'running' ? '任务已启动' : '任务已停止',
            'logged_at' => now(),
        ]);
        if ($next === 'running' && $task->resolveBoundProxy()) {
            PackageQuota::recordProxySuccessAndMaybePause((int) $task->tenant_id);
        }

        return $task;
    }

    public function logs(CrawlerTask $task): array
    {
        return $task->logs()->limit(50)->get()->map->toFrontendArray()->values()->all();
    }

    /**
     * 校验账号：归属租户、状态正常、已绑代理、平台在套餐白名单
     */
    private function assertExecutableAccount(int $tenantId, int $accountId): SocialAccount
    {
        $account = SocialAccount::query()->with('proxy')->find($accountId);
        if (!$account || (int) $account->tenant_id !== $tenantId) {
            throw new RuntimeException('所选社媒账号不存在或不属于当前租户');
        }
        if ((int) $account->account_status !== 1) {
            throw new RuntimeException('所选社媒账号状态异常，请选择状态正常的账号');
        }
        // 代理由平台公共池在启动时自动分配，不再要求账号预先绑定自有/手工 IP

        $setting = PackageQuota::settingForTenant($tenantId);
        $allowed = $setting->allow_platforms ?: [];
        $code = PlatformEnum::toPythonKey((int) $account->platform);
        if ($allowed && !in_array($code, $allowed, true)) {
            throw new RuntimeException('当前套餐不支持该平台账号，请升级套餐或更换账号');
        }

        return $account;
    }
}
