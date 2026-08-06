<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PackageSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 套餐权限管理（仅超级管理员）
 */
class PackageSettingController extends Controller
{
    public function __construct(private PackageSettingService $service)
    {
    }

    /** GET /api/package-setting/list */
    public function list()
    {
        return ApiResponse::success(['list' => $this->service->list()]);
    }

    /** POST /api/package-setting/save */
    public function save(Request $request)
    {
        // 合并全部入参：避免 validate 白名单丢弃 price_monthly / 增值开关
        $data = $request->validate([
            'package_type' => ['required_without:packageType', 'nullable', 'integer', 'in:1,2,3'],
            'packageType' => ['nullable', 'integer', 'in:1,2,3'],
            'price_monthly' => ['nullable', 'integer', 'min:0'],
            'priceMonthly' => ['nullable', 'integer', 'min:0'],
            'max_template_level' => ['nullable', 'integer', 'between:1,3'],
            'maxTemplateLevel' => ['nullable', 'integer', 'between:1,3'],
            'max_prompt' => ['nullable', 'integer', 'min:-1'],
            'maxPrompt' => ['nullable', 'integer', 'min:-1'],
            'max_knowledge' => ['nullable', 'integer', 'min:-1'],
            'maxKnowledge' => ['nullable', 'integer', 'min:-1'],
            'daily_ai_limit' => ['nullable', 'integer', 'min:-1'],
            'dailyAiLimit' => ['nullable', 'integer', 'min:-1'],
            'max_crawler_task' => ['nullable', 'integer', 'min:-1'],
            'maxCrawlerTask' => ['nullable', 'integer', 'min:-1'],
            'max_social_account' => ['nullable', 'integer', 'min:-1'],
            'maxSocialAccount' => ['nullable', 'integer', 'min:-1'],
            'max_proxy_ip' => ['nullable', 'integer', 'min:-1'],
            'maxProxyIp' => ['nullable', 'integer', 'min:-1'],
            'daily_proxy_request_limit' => ['nullable', 'integer', 'min:-1'],
            'dailyProxyRequestLimit' => ['nullable', 'integer', 'min:-1'],
            'allow_self_proxy' => ['nullable'],
            'allowSelfProxy' => ['nullable'],
            'allow_platforms' => ['nullable', 'array'],
            'allowPlatforms' => ['nullable', 'array'],
            'enable_account_ai_config' => ['nullable'],
            'enableAccountAiConfig' => ['nullable'],
            'enable_account_knowledge' => ['nullable'],
            'enableAccountKnowledge' => ['nullable'],
            'enable_custom_api_key' => ['nullable'],
            'enableCustomApiKey' => ['nullable'],
            'enable_sub_account' => ['nullable'],
            'enableSubAccount' => ['nullable'],
            'enable_human_behavior' => ['nullable'],
            'enableHumanBehavior' => ['nullable'],
            'enable_crm_auto_remind' => ['nullable'],
            'enableCrmAutoRemind' => ['nullable'],
            'enable_excel_export' => ['nullable'],
            'enableExcelExport' => ['nullable'],
            'enable_dedicated_ip_pool' => ['nullable'],
            'enableDedicatedIpPool' => ['nullable'],
            'enable_ip_risk_check' => ['nullable'],
            'enableIpRiskCheck' => ['nullable'],
            'enable_ip_rotate' => ['nullable'],
            'enableIpRotate' => ['nullable'],
            'enable_white_label' => ['nullable'],
            'enableWhiteLabel' => ['nullable'],
            'premium_features' => ['nullable', 'array'],
            'premiumFeatures' => ['nullable', 'array'],
            'remark' => ['nullable', 'string'],
            'reset' => ['nullable', 'boolean'],
        ]);

        try {
            if (!empty($data['reset'])) {
                $type = (int) ($data['package_type'] ?? $data['packageType']);
                $setting = $this->service->reset($type);

                return ApiResponse::success($setting->toFrontendArray(), '已重置为系统默认套餐配置');
            }

            $setting = $this->service->save($data);

            return ApiResponse::success($setting->toFrontendArray(), '套餐权限已保存');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/package-setting/tenant-quota/{tenantId}
     * 超管可查任意租户；租户账号仅可查自身
     */
    public function tenantQuota(Request $request, int $tenantId)
    {
        $user = $request->user();
        $allowed = $user->canAccessModule('tenants')
            || $user->canAccessModule('social-accounts')
            || $user->canAccessModule('proxy-ips')
            || $user->canAccessModule('finance');
        if (!$allowed) {
            return ApiResponse::forbidden('当前角色无权访问该模块');
        }
        if (!$user->isSuperAdmin() && (int) $user->tenant_id !== $tenantId) {
            return ApiResponse::forbidden('无权查看其他租户配额');
        }
        try {
            return ApiResponse::success(\App\Support\PackageQuota::quotaBriefForTenant($tenantId));
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
