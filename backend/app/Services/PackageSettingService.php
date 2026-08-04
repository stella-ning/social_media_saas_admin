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
            'max_template_level' => max(1, min(3, (int) ($data['max_template_level'] ?? $data['maxTemplateLevel'] ?? $type))),
            'max_prompt' => $this->nullableInt($data['max_prompt'] ?? $data['maxPrompt'] ?? null),
            'max_knowledge' => $this->nullableInt($data['max_knowledge'] ?? $data['maxKnowledge'] ?? null),
            'daily_ai_limit' => $this->nullableInt($data['daily_ai_limit'] ?? $data['dailyAiLimit'] ?? null),
            'max_crawler_task' => $this->nullableInt($data['max_crawler_task'] ?? $data['maxCrawlerTask'] ?? null),
            'max_social_account' => $this->nullableInt($data['max_social_account'] ?? $data['maxSocialAccount'] ?? null),
            'allow_platforms' => $data['allow_platforms'] ?? $data['allowPlatforms'] ?? ['xiaohongshu'],
            'enable_account_ai_config' => !empty($data['enable_account_ai_config'] ?? $data['enableAccountAiConfig'] ?? false) ? 1 : 0,
            'enable_account_knowledge' => !empty($data['enable_account_knowledge'] ?? $data['enableAccountKnowledge'] ?? false) ? 1 : 0,
            'enable_custom_api_key' => !empty($data['enable_custom_api_key'] ?? $data['enableCustomApiKey'] ?? false) ? 1 : 0,
            'remark' => $data['remark'] ?? $setting->remark,
        ]);
        $setting->save();

        Log::info('package_setting.saved', [
            'package_type' => $type,
            // 无密钥字段
        ]);

        // 套餐权限变更后，对该套餐下所有租户做模板降级对账
        $code = PackageSetting::packageCodeFromType($type);
        Tenant::query()->where('package', $code)->orderBy('id')->each(function (Tenant $t) {
            PackageQuota::reconcileTenantAiTemplate($t);
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
