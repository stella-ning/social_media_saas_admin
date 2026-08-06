<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 套餐权限配置 saas_package_setting
 * package_type: 1基础 / 2专业 / 3企业
 * 硬性规则：全部套餐 allow_self_proxy=0，爬虫仅用平台公共住宅代理池
 */
class PackageSetting extends Model
{
    protected $table = 'saas_package_setting';

    protected $fillable = [
        'package_type',
        'price_monthly',
        'max_template_level',
        'max_prompt',
        'max_knowledge',
        'daily_ai_limit',
        'max_crawler_task',
        'max_social_account',
        'max_proxy_ip',
        'daily_proxy_request_limit',
        'allow_self_proxy',
        'allow_platforms',
        'enable_account_ai_config',
        'enable_account_knowledge',
        'enable_custom_api_key',
        'premium_features',
        'enable_sub_account',
        'enable_human_behavior',
        'enable_crm_auto_remind',
        'enable_excel_export',
        'enable_dedicated_ip_pool',
        'enable_ip_risk_check',
        'enable_ip_rotate',
        'enable_white_label',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'package_type' => 'integer',
            'price_monthly' => 'integer',
            'max_template_level' => 'integer',
            'max_prompt' => 'integer',
            'max_knowledge' => 'integer',
            'daily_ai_limit' => 'integer',
            'max_crawler_task' => 'integer',
            'max_social_account' => 'integer',
            'max_proxy_ip' => 'integer',
            'daily_proxy_request_limit' => 'integer',
            'allow_self_proxy' => 'integer',
            'allow_platforms' => 'array',
            'enable_account_ai_config' => 'integer',
            'enable_account_knowledge' => 'integer',
            'enable_custom_api_key' => 'integer',
            'premium_features' => 'array',
            'enable_sub_account' => 'integer',
            'enable_human_behavior' => 'integer',
            'enable_crm_auto_remind' => 'integer',
            'enable_excel_export' => 'integer',
            'enable_dedicated_ip_pool' => 'integer',
            'enable_ip_risk_check' => 'integer',
            'enable_ip_rotate' => 'integer',
            'enable_white_label' => 'integer',
        ];
    }

    public const TYPE_BASIC = 1;
    public const TYPE_PRO = 2;
    public const TYPE_ENT = 3;
    public const PACKAGE_VERSION = '2026.08';

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

    /** 系统默认套餐参数（2026.08 平台公共代理托管版） */
    public static function systemDefaults(int $type): array
    {
        return match ($type) {
            self::TYPE_PRO => [
                'price_monthly' => 399,
                'max_template_level' => 2,
                'max_prompt' => 15,
                'max_knowledge' => 30,
                'daily_ai_limit' => 8000,
                'max_crawler_task' => 20,
                'max_social_account' => 15,
                'max_proxy_ip' => 15,
                'daily_proxy_request_limit' => 20000,
                'allow_self_proxy' => 0,
                'allow_platforms' => ['xiaohongshu', 'douyin'],
                'enable_account_ai_config' => 1,
                'enable_account_knowledge' => 1,
                'enable_custom_api_key' => 0,
                'enable_sub_account' => 0,
                'enable_human_behavior' => 1,
                'enable_crm_auto_remind' => 1,
                'enable_excel_export' => 1,
                'enable_dedicated_ip_pool' => 0,
                'enable_ip_risk_check' => 1,
                'enable_ip_rotate' => 0,
                'enable_white_label' => 0,
                'premium_features' => [
                    '账号独立 Prompt / 知识库',
                    'AI 意向识别 + 高意向推 CRM 跟进提醒',
                    '爬虫高级真人行为配置',
                    '每月一次爬虫故障优先排查',
                    '会话/线索 Excel 导出',
                    'IP 风险检测与账号-IP 风控隔离',
                ],
                'remark' => '专业版 ¥399/月 · 平台公共代理池 · 禁止自有IP',
            ],
            self::TYPE_ENT => [
                'price_monthly' => 1099,
                'max_template_level' => 3,
                'max_prompt' => null,
                'max_knowledge' => null,
                'daily_ai_limit' => null,
                'max_crawler_task' => null,
                'max_social_account' => null,
                'max_proxy_ip' => null,
                'daily_proxy_request_limit' => null,
                'allow_self_proxy' => 0,
                'allow_platforms' => ['xiaohongshu', 'douyin', 'channels'],
                'enable_account_ai_config' => 1,
                'enable_account_knowledge' => 1,
                'enable_custom_api_key' => 1,
                'enable_sub_account' => 1,
                'enable_human_behavior' => 1,
                'enable_crm_auto_remind' => 1,
                'enable_excel_export' => 1,
                'enable_dedicated_ip_pool' => 1,
                'enable_ip_risk_check' => 1,
                'enable_ip_rotate' => 1,
                'enable_white_label' => 1,
                'premium_features' => [
                    '租户专属隔离平台代理 IP 池',
                    '接入私人大模型 API-Key（Token 自担）',
                    '多员工子账号分级权限',
                    '爬虫接口优先适配',
                    '一对一行业 Prompt 定制',
                    'CRM 线索自动分发与定时回访',
                    '白标去平台标识',
                    '三平台独立 IP 分配 / 批量检测 / 自动轮换',
                ],
                'remark' => '企业版 ¥1099/月 · 专属隔离公共池 · 禁止自有IP',
            ],
            default => [
                'price_monthly' => 139,
                'max_template_level' => 1,
                'max_prompt' => 3,
                'max_knowledge' => 5,
                'daily_ai_limit' => 800,
                'max_crawler_task' => 5,
                'max_social_account' => 3,
                'max_proxy_ip' => 3,
                'daily_proxy_request_limit' => 3000,
                'allow_self_proxy' => 0,
                'allow_platforms' => ['xiaohongshu'],
                'enable_account_ai_config' => 0,
                'enable_account_knowledge' => 0,
                'enable_custom_api_key' => 0,
                'enable_sub_account' => 0,
                'enable_human_behavior' => 0,
                'enable_crm_auto_remind' => 0,
                'enable_excel_export' => 0,
                'enable_dedicated_ip_pool' => 0,
                'enable_ip_risk_check' => 0,
                'enable_ip_rotate' => 0,
                'enable_white_label' => 0,
                'premium_features' => [
                    '每日 1 次 AI 优质线索筛选',
                    '普通客服工单通道',
                    '定期行业基础 Prompt 更新',
                    'AI 回复强制真人客服口吻（禁 AI/机器人字眼）',
                ],
                'remark' => '基础版 ¥139/月 · 仅小红书 · 平台公共代理池',
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
            'packageVersion' => self::PACKAGE_VERSION,
            'priceMonthly' => (int) ($this->price_monthly ?? 0),
            'maxTemplateLevel' => (int) $this->max_template_level,
            'allowedLevels' => range(1, max(1, (int) $this->max_template_level)),
            'maxPrompt' => $this->max_prompt,
            'maxKnowledge' => $this->max_knowledge,
            'dailyAiLimit' => $this->daily_ai_limit,
            'maxCrawlerTask' => $this->max_crawler_task,
            'maxSocialAccount' => $this->max_social_account,
            'maxProxyIp' => $this->max_proxy_ip,
            'dailyProxyRequestLimit' => $this->daily_proxy_request_limit,
            'allowSelfProxy' => false, // 全局硬关
            'ipHostingNote' => '全部 IP 资源由平台统一托管的公共住宅代理池提供，禁止租户上传自有代理',
            'allowPlatforms' => $this->allow_platforms ?: [],
            'enableAccountAiConfig' => (int) $this->enable_account_ai_config === 1,
            'enableAccountKnowledge' => (int) $this->enable_account_knowledge === 1,
            'enableCustomApiKey' => (int) $this->enable_custom_api_key === 1,
            'enableSubAccount' => (int) ($this->enable_sub_account ?? 0) === 1,
            'enableHumanBehavior' => (int) ($this->enable_human_behavior ?? 0) === 1,
            'enableCrmAutoRemind' => (int) ($this->enable_crm_auto_remind ?? 0) === 1,
            'enableExcelExport' => (int) ($this->enable_excel_export ?? 0) === 1,
            'enableDedicatedIpPool' => (int) ($this->enable_dedicated_ip_pool ?? 0) === 1,
            'enableIpRiskCheck' => (int) ($this->enable_ip_risk_check ?? 0) === 1,
            'enableIpRotate' => (int) ($this->enable_ip_rotate ?? 0) === 1,
            'enableWhiteLabel' => (int) ($this->enable_white_label ?? 0) === 1,
            'premiumFeatures' => $this->premium_features ?: [],
            'remark' => $this->remark,
            'updatedAt' => optional($this->updated_at)?->format('Y-m-d H:i'),
        ];
    }
}
