<?php

namespace App\Support;

use App\Models\PackageSetting;
use App\Models\Tenant;

/**
 * 租户套餐 ↔ AI 参数模板等级映射
 * 优先读取 saas_package_setting.max_template_level；无配置时回退硬编码
 */
class PackageTemplateLevel
{
    /** 兜底：套餐代码 → 允许的最大 template_level */
    public const PACKAGE_MAX_LEVEL = [
        'basic' => 1,
        'pro' => 2,
        'ent' => 3,
    ];

    public const LEVEL_LABELS = [
        1 => '基础版',
        2 => '专业版',
        3 => '企业版',
    ];

    public static function maxLevelForPackage(string $package): int
    {
        $setting = PackageSetting::findByPackageCode($package);
        if ($setting) {
            return max(1, min(3, (int) $setting->max_template_level));
        }

        return self::PACKAGE_MAX_LEVEL[$package] ?? 1;
    }

    /**
     * @return int[]
     */
    public static function allowedLevels(string $package): array
    {
        return range(1, self::maxLevelForPackage($package));
    }

    /** 基于租户（含套餐配置表） */
    public static function allowedLevelsForTenant(Tenant|int $tenant): array
    {
        return PackageQuota::allowedTemplateLevels($tenant);
    }

    public static function levelLabel(int $level): string
    {
        return self::LEVEL_LABELS[$level] ?? (string) $level;
    }

    public static function normalizeLevel(mixed $level): int
    {
        $v = (int) $level;
        if ($v < 1 || $v > 3) {
            return 1;
        }

        return $v;
    }
}
