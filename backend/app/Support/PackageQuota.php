<?php

namespace App\Support;

use App\Models\AiParamTemplate;
use App\Models\AiPromptTemplate;
use App\Models\CrawlerTask;
use App\Models\KnowledgeDoc;
use App\Models\PackageSetting;
use App\Models\SocialAccount;
use App\Models\Tenant;
use RuntimeException;

/**
 * 套餐权限校验工具
 * - 读取 saas_package_setting
 * - 校验 Prompt / 知识库 / 爬虫 / 社媒账号上限
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
