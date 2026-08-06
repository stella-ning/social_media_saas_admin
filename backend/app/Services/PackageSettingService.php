<?php

namespace App\Services;

use App\Models\PackageSetting;
use App\Models\Tenant;
use App\Support\PackageQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 套餐权限配置服务（仅超管可改）
 */
class PackageSettingService
{
    public function list(): array
    {
        return PackageSetting::query()
            ->orderBy('package_type')
            ->get()
            ->map(fn (PackageSetting $s) => $s->toFrontendArray())
            ->values()
            ->all();
    }

    /**
     * 保存单套餐配置
     */
    public function save(array $data): PackageSetting
    {
        $type = (int) ($data['package_type'] ?? $data['packageType'] ?? 0);
        if (!in_array($type, [1, 2, 3], true)) {
            throw new RuntimeException('无效的套餐类型');
        }

        $setting = PackageSetting::findByType($type);
        if (!$setting) {
            $setting = new PackageSetting(['package_type' => $type]);
        }

        $setting->fill([
            'price_monthly' => max(0, (int) ($data['price_monthly'] ?? $data['priceMonthly'] ?? $setting->price_monthly ?? 0)),
            'max_template_level' => max(1, min(3, (int) ($data['max_template_level'] ?? $data['maxTemplateLevel'] ?? $type))),
            'max_prompt' => $this->nullableInt($data['max_prompt'] ?? $data['maxPrompt'] ?? null),
            'max_knowledge' => $this->nullableInt($data['max_knowledge'] ?? $data['maxKnowledge'] ?? null),
            'daily_ai_limit' => $this->nullableInt($data['daily_ai_limit'] ?? $data['dailyAiLimit'] ?? null),
            'max_crawler_task' => $this->nullableInt($data['max_crawler_task'] ?? $data['maxCrawlerTask'] ?? null),
            'max_social_account' => $this->nullableInt($data['max_social_account'] ?? $data['maxSocialAccount'] ?? null),
            'max_proxy_ip' => $this->nullableInt($data['max_proxy_ip'] ?? $data['maxProxyIp'] ?? null),
            'daily_proxy_request_limit' => $this->nullableInt($data['daily_proxy_request_limit'] ?? $data['dailyProxyRequestLimit'] ?? null),
            'allow_self_proxy' => 0, // 全局强制关闭
            'allow_platforms' => $data['allow_platforms'] ?? $data['allowPlatforms'] ?? ['xiaohongshu'],
            'enable_account_ai_config' => !empty($data['enable_account_ai_config'] ?? $data['enableAccountAiConfig'] ?? false) ? 1 : 0,
            'enable_account_knowledge' => !empty($data['enable_account_knowledge'] ?? $data['enableAccountKnowledge'] ?? false) ? 1 : 0,
            'enable_custom_api_key' => !empty($data['enable_custom_api_key'] ?? $data['enableCustomApiKey'] ?? false) ? 1 : 0,
            'enable_sub_account' => !empty($data['enable_sub_account'] ?? $data['enableSubAccount'] ?? false) ? 1 : 0,
            'enable_human_behavior' => !empty($data['enable_human_behavior'] ?? $data['enableHumanBehavior'] ?? false) ? 1 : 0,
            'enable_crm_auto_remind' => !empty($data['enable_crm_auto_remind'] ?? $data['enableCrmAutoRemind'] ?? false) ? 1 : 0,
            'enable_excel_export' => !empty($data['enable_excel_export'] ?? $data['enableExcelExport'] ?? false) ? 1 : 0,
            'enable_dedicated_ip_pool' => !empty($data['enable_dedicated_ip_pool'] ?? $data['enableDedicatedIpPool'] ?? false) ? 1 : 0,
            'enable_ip_risk_check' => !empty($data['enable_ip_risk_check'] ?? $data['enableIpRiskCheck'] ?? false) ? 1 : 0,
            'enable_ip_rotate' => !empty($data['enable_ip_rotate'] ?? $data['enableIpRotate'] ?? false) ? 1 : 0,
            'enable_white_label' => !empty($data['enable_white_label'] ?? $data['enableWhiteLabel'] ?? false) ? 1 : 0,
            'premium_features' => $data['premium_features'] ?? $data['premiumFeatures'] ?? $setting->premium_features,
            'remark' => $data['remark'] ?? $setting->remark,
        ]);
        $setting->save();

        Log::info('package_setting.saved', [
            'package_type' => $type,
            // 无密钥字段
        ]);

        // 套餐权限变更后，对该套餐下所有租户做模板降级对账 + 爬虫超额暂停
        $code = PackageSetting::packageCodeFromType($type);
        Tenant::query()->where('package', $code)->orderBy('id')->each(function (Tenant $t) {
            PackageQuota::reconcileTenantAiTemplate($t);
            PackageQuota::reconcileCrawlerTasksOnDowngrade($t);
        });

        return $setting->fresh();
    }

    /** 重置为系统默认 */
    public function reset(int $packageType): PackageSetting
    {
        $defaults = PackageSetting::systemDefaults($packageType);
        $defaults['package_type'] = $packageType;

        return $this->save($defaults);
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '' || $v === -1 || $v === '-1') {
            return null;
        }

        return max(0, (int) $v);
    }
}
