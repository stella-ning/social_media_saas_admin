<?php

namespace App\Support;

use App\Models\AiParamTemplate;
use App\Models\AiPromptTemplate;
use App\Models\CrawlerTask;
use App\Models\CrawlerTaskLog;
use App\Models\KnowledgeDoc;
use App\Models\PackageSetting;
use App\Models\ProxyIp;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\TenantProxy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 套餐权限校验工具
 * - 读取 saas_package_setting
 * - 校验 Prompt / 知识库 / 爬虫 / 社媒账号 / 代理IP 上限
 * - 每日代理请求配额：达限后暂停租户爬虫任务
 * - 提供模板等级过滤与降级处理
 */
class PackageQuota
{
    public static function settingForTenant(Tenant|int $tenant): PackageSetting
    {
        if (!$tenant instanceof Tenant) {
            $tenant = Tenant::query()->findOrFail($tenant);
        }
        $setting = PackageSetting::findByPackageCode((string) $tenant->package);
        if (!$setting) {
            // 兜底：按 basic 默认
            $setting = new PackageSetting(PackageSetting::systemDefaults(PackageSetting::TYPE_BASIC));
            $setting->package_type = PackageSetting::TYPE_BASIC;
        }

        return $setting;
    }

    /** @return int[] */
    public static function allowedTemplateLevels(Tenant|int $tenant): array
    {
        $setting = self::settingForTenant($tenant);
        $max = max(1, min(3, (int) $setting->max_template_level));

        return range(1, $max);
    }

    public static function assertCanCreatePrompt(int $tenantId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $setting = self::settingForTenant($tenant);
        if ($setting->max_prompt === null) {
            return;
        }
        $count = AiPromptTemplate::query()->where('tenant_id', $tenantId)->count();
        if ($count >= (int) $setting->max_prompt) {
            throw new RuntimeException(
                "当前套餐 Prompt 模板上限为 {$setting->max_prompt} 条，已达上限，请升级套餐或删除旧模板"
            );
        }
    }

    public static function assertCanCreateKnowledge(int $tenantId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $setting = self::settingForTenant($tenant);
        if ($setting->max_knowledge === null) {
            return;
        }
        $count = KnowledgeDoc::query()->where('tenant_id', $tenantId)->count();
        if ($count >= (int) $setting->max_knowledge) {
            throw new RuntimeException(
                "当前套餐知识库文档上限为 {$setting->max_knowledge} 条，已达上限，请升级套餐或删除旧文档"
            );
        }
    }

    public static function assertCanCreateCrawlerTask(int $tenantId): void
    {
        self::assertPackageActive($tenantId);
        $tenant = Tenant::query()->findOrFail($tenantId);
        $setting = self::settingForTenant($tenant);
        if ($setting->max_crawler_task === null) {
            return;
        }
        $count = CrawlerTask::query()->where('tenant_id', $tenantId)->count();
        if ($count >= (int) $setting->max_crawler_task) {
            throw new RuntimeException(
                "当前套餐爬虫任务上限为 {$setting->max_crawler_task} 个，已达上限，请升级套餐或停止旧任务后删除"
            );
        }
    }

    /** 今日已用 AI 调用次数（消息/生成统一计数） */
    public static function dailyAiUsed(int $tenantId): int
    {
        return (int) Cache::get(self::dailyAiCacheKey($tenantId), 0);
    }

    public static function assertDailyAiAvailable(int $tenantId): void
    {
        $setting = self::settingForTenant($tenantId);
        if ($setting->daily_ai_limit === null) {
            return;
        }
        $used = self::dailyAiUsed($tenantId);
        $limit = (int) $setting->daily_ai_limit;
        if ($used >= $limit) {
            throw new RuntimeException(
                "今日 AI 调用已达套餐上限（{$limit} 次），已关闭自动回复，请明日再试或升级套餐"
            );
        }
    }

    /**
     * 记录一次 AI 调用；达限后关闭该租户全部会话的 AI 自动回复
     *
     * @return array{used:int,limit:?int,disabled:bool}
     */
    public static function recordAiCallAndMaybeDisableAutoReply(int $tenantId): array
    {
        $setting = self::settingForTenant($tenantId);
        $limit = $setting->daily_ai_limit;
        $key = self::dailyAiCacheKey($tenantId);
        if (!Cache::has($key)) {
            Cache::put($key, 0, now()->endOfDay());
        }
        $used = (int) Cache::increment($key);
        $disabled = false;
        if ($limit !== null && $used >= (int) $limit) {
            \App\Models\MessageSession::query()
                ->where('tenant_id', $tenantId)
                ->where('ai_auto_reply', 1)
                ->update(['ai_auto_reply' => 0]);
            $disabled = true;
            Log::info('package_quota.ai_daily_limit_disable_auto_reply', [
                'tenant_id' => $tenantId,
                'used' => $used,
                'limit' => $limit,
            ]);
        }

        return [
            'used' => $used,
            'limit' => $limit === null ? null : (int) $limit,
            'disabled' => $disabled,
        ];
    }

    /** 记录一次代理/网页访问请求（主页核验、评论发送等） */
    public static function recordProxyRequest(int $tenantId): array
    {
        return self::recordProxySuccessAndMaybePause($tenantId);
    }

    private static function dailyAiCacheKey(int $tenantId): string
    {
        return 'tenant_ai_req:'.$tenantId.':'.now()->format('Ymd');
    }

    /**
     * 恢复运行中任务前：运行中数量不可超过套餐上限
     */
    public static function assertCanResumeCrawlerTask(int $tenantId): void
    {
        $setting = self::settingForTenant($tenantId);
        if ($setting->max_crawler_task === null) {
            return;
        }
        $running = CrawlerTask::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'running')
            ->count();
        if ($running >= (int) $setting->max_crawler_task) {
            throw new RuntimeException(
                "当前套餐同时运行中的爬虫任务上限为 {$setting->max_crawler_task} 个，请先暂停其他任务或升级套餐"
            );
        }
    }

    /**
     * 套餐降级 / 权限收紧后：运行中任务数超出上限则自动暂停多余任务（保留最早创建的）
     *
     * @return int 暂停数量
     */
    public static function reconcileCrawlerTasksOnDowngrade(Tenant|int $tenant): int
    {
        if (!$tenant instanceof Tenant) {
            $tenant = Tenant::query()->findOrFail($tenant);
        }
        $setting = self::settingForTenant($tenant);
        if ($setting->max_crawler_task === null) {
            return 0;
        }

        $max = (int) $setting->max_crawler_task;
        $running = CrawlerTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'running')
            ->orderBy('id')
            ->get();

        if ($running->count() <= $max) {
            return 0;
        }

        $toPause = $running->slice($max);
        foreach ($toPause as $task) {
            $task->update(['status' => 'paused']);
            CrawlerTaskLog::create([
                'task_id' => $task->id,
                'type' => 'warning',
                'content' => "套餐降级：运行中任务超过上限（{$max}），系统自动暂停多余任务",
                'logged_at' => now(),
            ]);
        }

        Log::info('package_quota.crawler_downgrade_pause', [
            'tenant_id' => $tenant->id,
            'max' => $max,
            'paused_count' => $toPause->count(),
        ]);

        return $toPause->count();
    }

    public static function assertCanBindSocialAccount(int $tenantId, int|string $platform): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $setting = self::settingForTenant($tenant);

        // 平台限制
        $code = self::normalizePlatformCode($platform);
        $allowed = $setting->allow_platforms ?: [];
        if ($allowed && !in_array($code, $allowed, true)) {
            $label = PlatformEnum::MAP[PlatformEnum::toCode($code === 'xiaohongshu' ? '小红书' : ($code === 'douyin' ? '抖音' : '视频号'))] ?? $code;
            throw new RuntimeException("当前套餐不支持绑定「{$label}」账号，请升级套餐");
        }

        if ($setting->max_social_account === null) {
            return;
        }
        $count = SocialAccount::query()->where('tenant_id', $tenantId)->count();
        if ($count >= (int) $setting->max_social_account) {
            throw new RuntimeException(
                "当前套餐社媒账号上限为 {$setting->max_social_account} 个，已达上限，请升级套餐或解绑旧账号"
            );
        }
    }

    public static function assertAccountAiConfigEnabled(int $tenantId): void
    {
        $setting = self::settingForTenant($tenantId);
        if ((int) $setting->enable_account_ai_config !== 1) {
            throw new RuntimeException('当前套餐未开启小红书账号独立 AI 配置权限，账号将强制继承租户 AI 模板');
        }
    }

    public static function assertAccountKnowledgeEnabled(int $tenantId): void
    {
        $setting = self::settingForTenant($tenantId);
        if ((int) $setting->enable_account_knowledge !== 1) {
            throw new RuntimeException('当前套餐未开启账号专属知识库权限');
        }
    }

    /**
     * 有效代理配额：租户级字段优先，未设置则回退 saas_package_setting
     * 硬性规则：allow_self_proxy 全局恒为 0（禁止租户自有代理）
     *
     * @return array{max_proxy_ip:?int,daily_proxy_request_limit:?int,allow_self_proxy:int}
     */
    public static function effectiveProxyQuota(Tenant|int $tenant): array
    {
        if (!$tenant instanceof Tenant) {
            $tenant = Tenant::query()->findOrFail($tenant);
        }
        $setting = self::settingForTenant($tenant);

        return [
            'max_proxy_ip' => $tenant->max_proxy_ip !== null
                ? (int) $tenant->max_proxy_ip
                : $setting->max_proxy_ip,
            'daily_proxy_request_limit' => $tenant->daily_proxy_request_limit !== null
                ? (int) $tenant->daily_proxy_request_limit
                : $setting->daily_proxy_request_limit,
            'allow_self_proxy' => 0,
        ];
    }

    /** 全局禁止租户上传/接入自有代理 IP */
    public static function assertSelfProxyAllowed(int $tenantId): void
    {
        throw new RuntimeException(
            '平台已关闭租户自有代理上传：全部爬虫网络请求统一使用平台公共住宅代理 IP 池'
        );
    }

    public static function allowsSelfProxy(int $tenantId): bool
    {
        return false;
    }

    /** 套餐是否在有效期内（未设置到期时间视为有效） */
    public static function assertPackageActive(Tenant|int $tenant): void
    {
        if (!$tenant instanceof Tenant) {
            $tenant = Tenant::query()->findOrFail($tenant);
        }
        if ($tenant->package_expires_at && $tenant->package_expires_at->isPast()) {
            throw new RuntimeException('套餐已到期，爬虫任务 / AI 自动回复 / 增值特权已暂停，请续费后使用');
        }
        if ((int) $tenant->status !== 1) {
            throw new RuntimeException('租户已停用，无法使用平台资源');
        }
    }

    public static function isPackageExpired(Tenant|int $tenant): bool
    {
        if (!$tenant instanceof Tenant) {
            $tenant = Tenant::query()->findOrFail($tenant);
        }

        return $tenant->package_expires_at && $tenant->package_expires_at->isPast();
    }

    /** 套餐到期：暂停爬虫 + 关闭 AI 自动回复 */
    public static function pausePrivilegesOnExpiry(Tenant $tenant): void
    {
        if (!self::isPackageExpired($tenant)) {
            return;
        }
        $tasks = CrawlerTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'running')
            ->get();
        foreach ($tasks as $task) {
            $task->update(['status' => 'paused']);
            CrawlerTaskLog::create([
                'task_id' => $task->id,
                'type' => 'warning',
                'content' => '套餐已到期，系统自动暂停爬虫任务',
                'logged_at' => now(),
            ]);
        }
        \App\Models\MessageSession::query()
            ->where('tenant_id', $tenant->id)
            ->update(['ai_auto_reply' => false]);
    }

    public static function assertFeatureEnabled(int $tenantId, string $flag, string $label): void
    {
        self::assertPackageActive($tenantId);
        $setting = self::settingForTenant($tenantId);
        if ((int) ($setting->{$flag} ?? 0) !== 1) {
            throw new RuntimeException("当前套餐未开通「{$label}」，请升级套餐");
        }
    }

    /**
     * 租户绑定代理 IP 数量上限校验（saas_tenant_proxy ∪ proxy_ips.tenant_id）
     */
    public static function assertCanBindProxyIp(int $tenantId, ?int $excludeProxyIpId = null): void
    {
        $quota = self::effectiveProxyQuota($tenantId);
        if ($quota['max_proxy_ip'] === null) {
            return;
        }

        $relIds = TenantProxy::query()->where('tenant_id', $tenantId)->pluck('proxy_ip_id')->all();
        $directIds = ProxyIp::query()->where('tenant_id', $tenantId)->pluck('id')->all();
        $ids = array_values(array_unique(array_merge($relIds, $directIds)));
        if ($excludeProxyIpId) {
            $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $excludeProxyIpId));
        }

        $max = (int) $quota['max_proxy_ip'];
        if (count($ids) >= $max) {
            throw new RuntimeException(
                "当前套餐代理 IP 绑定上限为 {$max} 个，已达上限，请升级套餐或解绑旧 IP"
            );
        }
    }

    /** 今日已用代理成功请求次数 */
    public static function dailyProxyRequestUsed(int $tenantId): int
    {
        return (int) Cache::get(self::dailyProxyCacheKey($tenantId), 0);
    }

    /**
     * 启动/创建爬虫前：校验每日代理请求配额是否已耗尽
     */
    public static function assertDailyProxyRequestAvailable(int $tenantId): void
    {
        $quota = self::effectiveProxyQuota($tenantId);
        if ($quota['daily_proxy_request_limit'] === null) {
            return;
        }
        $used = self::dailyProxyRequestUsed($tenantId);
        $limit = (int) $quota['daily_proxy_request_limit'];
        if ($used >= $limit) {
            throw new RuntimeException(
                "今日代理 IP 成功请求已达套餐上限（{$limit} 次），爬虫任务已暂停，请明日再试或升级套餐"
            );
        }
    }

    /**
     * 记录一次代理 IP 成功请求；达限后自动暂停该租户全部运行中爬虫
     *
     * @return array{used:int,limit:?int,paused:bool}
     */
    public static function recordProxySuccessAndMaybePause(int $tenantId): array
    {
        $quota = self::effectiveProxyQuota($tenantId);
        $limit = $quota['daily_proxy_request_limit'];
        $key = self::dailyProxyCacheKey($tenantId);

        if (!Cache::has($key)) {
            Cache::put($key, 0, now()->endOfDay());
        }
        $used = (int) Cache::increment($key);

        $paused = false;
        if ($limit !== null && $used >= (int) $limit) {
            $paused = self::pauseTenantCrawlerTasks($tenantId, (int) $limit, $used);
        }

        return [
            'used' => $used,
            'limit' => $limit === null ? null : (int) $limit,
            'paused' => $paused,
        ];
    }

    /**
     * 暂停租户全部 running 爬虫任务（代理日配额耗尽）
     */
    public static function pauseTenantCrawlerTasks(int $tenantId, int $limit, int $used): bool
    {
        $tasks = CrawlerTask::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'running')
            ->get();

        if ($tasks->isEmpty()) {
            return false;
        }

        foreach ($tasks as $task) {
            $task->update(['status' => 'paused']);
            CrawlerTaskLog::create([
                'task_id' => $task->id,
                'type' => 'warning',
                'content' => "代理 IP 日请求配额已达上限（{$used}/{$limit}），系统自动暂停任务",
                'logged_at' => now(),
            ]);
        }

        Log::info('package_quota.proxy_daily_limit_pause', [
            'tenant_id' => $tenantId,
            'used' => $used,
            'limit' => $limit,
            'paused_count' => $tasks->count(),
        ]);

        return true;
    }

    /**
     * 租户套餐配额摘要（前端代理页 / 套餐门禁）
     */
    public static function quotaBriefForTenant(int $tenantId): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $setting = self::settingForTenant($tenant);
        $quota = self::effectiveProxyQuota($tenant);
        $bound = self::boundProxyIpCount($tenantId);

        return [
            'tenantId' => $tenantId,
            'package' => $tenant->package,
            'packageLabel' => PackageSetting::LABEL_MAP[PackageSetting::typeFromPackageCode((string) $tenant->package)] ?? '',
            'maxProxyIp' => $quota['max_proxy_ip'],
            'boundProxyIp' => $bound,
            'dailyProxyRequestLimit' => $quota['daily_proxy_request_limit'],
            'dailyProxyRequestUsed' => self::dailyProxyRequestUsed($tenantId),
            'allowSelfProxy' => false,
            'ipHostingNote' => 'IP 由平台公共住宅代理池统一托管，禁止租户上传自有代理',
            'allowPlatforms' => $setting->allow_platforms ?: [],
            'maxCrawlerTask' => $setting->max_crawler_task,
            'enableDedicatedIpPool' => (int) ($setting->enable_dedicated_ip_pool ?? 0) === 1,
            'enableIpRiskCheck' => (int) ($setting->enable_ip_risk_check ?? 0) === 1,
            'enableIpRotate' => (int) ($setting->enable_ip_rotate ?? 0) === 1,
            'packageExpiresAt' => optional($tenant->package_expires_at)?->format('Y-m-d'),
            'packageExpired' => self::isPackageExpired($tenant),
        ];
    }

    public static function boundProxyIpCount(int $tenantId): int
    {
        $relIds = TenantProxy::query()->where('tenant_id', $tenantId)->pluck('proxy_ip_id')->all();
        $directIds = ProxyIp::query()->where('tenant_id', $tenantId)->pluck('id')->all();

        return count(array_unique(array_merge($relIds, $directIds)));
    }

    /**
     * 租户套餐变更后：若当前启用模板等级超限，自动降级到第一条可用基础等级模板
     */
    public static function reconcileTenantAiTemplate(Tenant $tenant): void
    {
        $levels = self::allowedTemplateLevels($tenant);
        $currentId = $tenant->current_ai_param_template_id;
        if (!$currentId) {
            return;
        }

        $current = AiParamTemplate::query()
            ->where('id', $currentId)
            ->where('tenant_id', $tenant->id)
            ->first();

        $level = (int) ($current?->template_level ?? 0);
        if ($current && in_array($level, $levels, true)) {
            return;
        }

        // 超限或模板已失效 → 选第一条可用最低等级模板
        $fallback = AiParamTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('template_level', $levels)
            ->orderBy('template_level')
            ->orderBy('id')
            ->first();

        $tenant->current_ai_param_template_id = $fallback?->id;
        $tenant->save();

        AiParamTemplate::query()->where('tenant_id', $tenant->id)->update(['is_default' => 0]);
        if ($fallback) {
            $fallback->update(['is_default' => 1]);
        }
    }

    private static function dailyProxyCacheKey(int $tenantId): string
    {
        return 'tenant_proxy_req:'.$tenantId.':'.now()->format('Ymd');
    }

    private static function normalizePlatformCode(int|string $platform): string
    {
        if (is_int($platform) || ctype_digit((string) $platform)) {
            return match ((int) $platform) {
                PlatformEnum::DOUYIN => 'douyin',
                PlatformEnum::CHANNELS => 'channels',
                default => 'xiaohongshu',
            };
        }
        $p = strtolower(trim((string) $platform));
        if (in_array($p, ['小红书', 'xhs', 'xiaohongshu'], true)) {
            return 'xiaohongshu';
        }
        if (in_array($p, ['抖音', 'douyin'], true)) {
            return 'douyin';
        }
        if (in_array($p, ['视频号', 'channels', 'shipinhao'], true)) {
            return 'channels';
        }

        try {
            return PlatformEnum::toPythonKey(PlatformEnum::toCode($platform));
        } catch (\Throwable) {
            return $p;
        }
    }
}
