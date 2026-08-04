<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 套餐权限配置 saas_package_setting
 * package_type: 1基础 / 2专业 / 3企业
 */
class PackageSetting extends Model
{
    protected $table = 'saas_package_setting';

    protected $fillable = [
        'package_type',
        'max_template_level',
        'max_prompt',
        'max_knowledge',
        'daily_ai_limit',
        'max_crawler_task',
        'max_social_account',
        'allow_platforms',
        'enable_account_ai_config',
        'enable_account_knowledge',
        'enable_custom_api_key',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'package_type' => 'integer',
            'max_template_level' => 'integer',
            'max_prompt' => 'integer',
            'max_knowledge' => 'integer',
            'daily_ai_limit' => 'integer',
            'max_crawler_task' => 'integer',
            'max_social_account' => 'integer',
            'allow_platforms' => 'array',
            'enable_account_ai_config' => 'integer',
            'enable_account_knowledge' => 'integer',
            'enable_custom_api_key' => 'integer',
        ];
    }

    public const TYPE_BASIC = 1;
    public const TYPE_PRO = 2;
    public const TYPE_ENT = 3;

    public const CODE_MAP = [
        'basic' => self::TYPE_BASIC,
        'pro' => self::TYPE_PRO,
        'ent' => self::TYPE_ENT,
    ];

    public const LABEL_MAP = [
        self::TYPE_BASIC => '基础版',
        self::TYPE_PRO => '专业版',
        self::TYPE_ENT => '企业版',
    ];

    public static function typeFromPackageCode(string $code): int
    {
        return self::CODE_MAP[$code] ?? self::TYPE_BASIC;
    }

    public static function packageCodeFromType(int $type): string
    {
        return array_flip(self::CODE_MAP)[$type] ?? 'basic';
    }

    public static function findByPackageCode(string $code): ?self
    {
        return static::query()->where('package_type', self::typeFromPackageCode($code))->first();
    }

    public static function findByType(int $type): ?self
    {
        return static::query()->where('package_type', $type)->first();
    }

    /** 系统默认套餐参数（重置用） */
    public static function systemDefaults(int $type): array
    {
        return match ($type) {
            self::TYPE_PRO => [
                'max_template_level' => 2,
                'max_prompt' => 15,
                'max_knowledge' => 30,
                'daily_ai_limit' => 8000,
                'max_crawler_task' => 30,
                'max_social_account' => 15,
                'allow_platforms' => ['xiaohongshu', 'douyin'],
                'enable_account_ai_config' => 1,
                'enable_account_knowledge' => 0,
                'enable_custom_api_key' => 0,
                'remark' => '专业版默认配置',
            ],
            self::TYPE_ENT => [
                'max_template_level' => 3,
                'max_prompt' => null,
                'max_knowledge' => null,
                'daily_ai_limit' => null,
                'max_crawler_task' => null,
                'max_social_account' => null,
                'allow_platforms' => ['xiaohongshu', 'douyin', 'channels'],
                'enable_account_ai_config' => 1,
                'enable_account_knowledge' => 1,
                'enable_custom_api_key' => 1,
                'remark' => '企业版默认配置',
            ],
            default => [
                'max_template_level' => 1,
                'max_prompt' => 3,
                'max_knowledge' => 5,
                'daily_ai_limit' => 800,
                'max_crawler_task' => 5,
                'max_social_account' => 3,
                'allow_platforms' => ['xiaohongshu'],
                'enable_account_ai_config' => 0,
                'enable_account_knowledge' => 0,
                'enable_custom_api_key' => 0,
                'remark' => '基础版默认配置',
            ],
        };
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'packageType' => (int) $this->package_type,
            'packageCode' => self::packageCodeFromType((int) $this->package_type),
            'packageLabel' => self::LABEL_MAP[(int) $this->package_type] ?? '',
            'maxTemplateLevel' => (int) $this->max_template_level,
            'allowedLevels' => range(1, max(1, (int) $this->max_template_level)),
            'maxPrompt' => $this->max_prompt,
            'maxKnowledge' => $this->max_knowledge,
            'dailyAiLimit' => $this->daily_ai_limit,
            'maxCrawlerTask' => $this->max_crawler_task,
            'maxSocialAccount' => $this->max_social_account,
            'allowPlatforms' => $this->allow_platforms ?: [],
            'enableAccountAiConfig' => (int) $this->enable_account_ai_config === 1,
            'enableAccountKnowledge' => (int) $this->enable_account_knowledge === 1,
            'enableCustomApiKey' => (int) $this->enable_custom_api_key === 1,
            'remark' => $this->remark,
            'updatedAt' => optional($this->updated_at)?->format('Y-m-d H:i'),
        ];
    }
}
