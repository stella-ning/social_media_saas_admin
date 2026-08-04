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
        $data = $request->validate([
            'package_type' => ['required_without:packageType', 'nullable', 'integer', 'in:1,2,3'],
            'packageType' => ['nullable', 'integer', 'in:1,2,3'],
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
            'allow_platforms' => ['nullable', 'array'],
            'allowPlatforms' => ['nullable', 'array'],
            'enable_account_ai_config' => ['nullable'],
            'enableAccountAiConfig' => ['nullable'],
            'enable_account_knowledge' => ['nullable'],
            'enableAccountKnowledge' => ['nullable'],
            'enable_custom_api_key' => ['nullable'],
            'enableCustomApiKey' => ['nullable'],
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
}
