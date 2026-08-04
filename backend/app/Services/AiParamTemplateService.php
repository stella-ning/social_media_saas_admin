<?php

namespace App\Services;

use App\Models\AiParamTemplate;
use App\Models\Tenant;
use App\Support\AesCrypto;
use App\Support\PackageTemplateLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 租户 AI 参数模板 CRUD
 * - 租户隔离
 * - API-Key AES-256 加密，日志禁止明文
 * - template_level：套餐下拉筛选等级（配置中心可创建任意等级）
 */
class AiParamTemplateService
{
    public function listByTenant(int $tenantId): array
    {
        return AiParamTemplate::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AiParamTemplate $t) => $t->toFrontendArray())
            ->values()
            ->all();
    }

    /**
     * 按租户套餐筛选可用 AI 参数模板（仅本租户数据）
     * 升级：读取 saas_package_setting.max_template_level 过滤
     */
    public function listByPackage(int $tenantId): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $setting = \App\Support\PackageQuota::settingForTenant($tenant);
        $levels = \App\Support\PackageQuota::allowedTemplateLevels($tenant);

        $list = AiParamTemplate::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('template_level', $levels ?: [0])
            ->orderBy('template_level')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AiParamTemplate $t) => $t->toFrontendArray())
            ->values()
            ->all();

        return [
            'tenantId' => $tenant->id,
            'package' => $tenant->package,
            'packageLabel' => match ($tenant->package) {
                'pro' => '专业版',
                'ent' => '企业版',
                default => '基础版',
            },
            'allowedLevels' => $levels,
            'maxTemplateLevel' => (int) $setting->max_template_level,
            'enableAccountAiConfig' => (int) $setting->enable_account_ai_config === 1,
            'currentAiParamTemplateId' => $tenant->current_ai_param_template_id,
            'list' => $list,
        ];
    }

    /**
     * 保存租户当前启用的 AI 参数模板；同步 is_default 便于兼容旧逻辑
     */
    public function saveCurrentTemplate(int $tenantId, ?int $templateId): Tenant
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        if ($templateId) {
            $tpl = AiParamTemplate::query()
                ->where('id', $templateId)
                ->where('tenant_id', $tenantId)
                ->first();
            if (!$tpl) {
                throw new RuntimeException('模板不存在或不属于当前租户');
            }

            // 套餐权限校验：对照 saas_package_setting
            $allowed = \App\Support\PackageQuota::allowedTemplateLevels($tenant);
            $level = PackageTemplateLevel::normalizeLevel($tpl->template_level);
            if (!in_array($level, $allowed, true)) {
                throw new RuntimeException('当前套餐无权启用该等级的 AI 参数模板，请升级套餐或选择更低等级模板');
            }

            return DB::transaction(function () use ($tenant, $tpl) {
                AiParamTemplate::query()
                    ->where('tenant_id', $tenant->id)
                    ->update(['is_default' => 0]);
                $tpl->is_default = 1;
                $tpl->save();

                $tenant->current_ai_param_template_id = $tpl->id;
                $tenant->save();

                Log::info('tenant.current_ai_template.saved', [
                    'tenant_id' => $tenant->id,
                    'template_id' => $tpl->id,
                ]);

                return $tenant->fresh('currentAiParamTemplate');
            });
        }

        // 清空 → 降级平台全局
        $tenant->current_ai_param_template_id = null;
        $tenant->save();
        AiParamTemplate::query()->where('tenant_id', $tenantId)->update(['is_default' => 0]);

        return $tenant->fresh('currentAiParamTemplate');
    }

    /**
     * 租户详情（套餐 + 当前启用模板名）
     */
    public function tenantAiInfo(int $tenantId): array
    {
        $tenant = Tenant::query()->with('currentAiParamTemplate')->findOrFail($tenantId);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'package' => $tenant->package,
            'packageLabel' => match ($tenant->package) {
                'pro' => '专业版',
                'ent' => '企业版',
                default => '基础版',
            },
            'currentAiParamTemplateId' => $tenant->current_ai_param_template_id,
            'currentAiParamTemplateName' => $tenant->currentAiParamTemplate?->template_name,
            'allowedLevels' => PackageTemplateLevel::allowedLevels((string) $tenant->package),
        ];
    }

    /**
     * 新增或编辑
     */
    public function save(array $data): AiParamTemplate
    {
        $tenantId = (int) $data['tenant_id'];
        $id = isset($data['id']) ? (int) $data['id'] : null;

        Log::info('ai_param_template.save', [
            'tenant_id' => $tenantId,
            'id' => $id,
            'template_name' => $data['template_name'] ?? null,
            'has_api_key' => !empty($data['api_key']),
            // 禁止打印 api_key
        ]);

        return DB::transaction(function () use ($data, $tenantId, $id) {
            if ($id) {
                $tpl = AiParamTemplate::query()
                    ->where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->firstOrFail();
            } else {
                $tpl = new AiParamTemplate(['tenant_id' => $tenantId]);
            }

            $tpl->template_name = $data['template_name'];
            $tpl->ai_model = $data['ai_model'];
            $tpl->api_base_url = $data['api_base_url'] ?? $tpl->api_base_url;
            $tpl->temperature = $data['temperature'] ?? $tpl->temperature ?? 0.7;
            $tpl->max_tokens = $data['max_tokens'] ?? $tpl->max_tokens ?? 2048;
            $tpl->top_p = $data['top_p'] ?? $tpl->top_p ?? 1.0;
            $tpl->daily_call_quota = $data['daily_call_quota'] ?? $tpl->daily_call_quota ?? 1000;
            $tpl->template_level = PackageTemplateLevel::normalizeLevel(
                $data['template_level'] ?? $tpl->template_level ?? 1
            );

            // 仅当传入非空且非掩码占位时更新密钥
            $apiKey = $data['api_key'] ?? null;
            if (is_string($apiKey) && $apiKey !== '' && $apiKey !== '********') {
                $tpl->encrypt_api_key = AesCrypto::encrypt($apiKey);
                unset($apiKey);
            } elseif (!$tpl->exists && empty($tpl->encrypt_api_key)) {
                throw new RuntimeException('新建模板必须填写 API-Key');
            }

            $setDefault = !empty($data['is_default']);
            $tpl->is_default = $setDefault ? 1 : (int) ($tpl->is_default ?? 0);
            $tpl->save();

            if ($setDefault) {
                $this->setDefault($tenantId, $tpl->id);
            }

            return $tpl->fresh();
        });
    }

    public function setDefault(int $tenantId, int $templateId): AiParamTemplate
    {
        return DB::transaction(function () use ($tenantId, $templateId) {
            $tpl = AiParamTemplate::query()
                ->where('id', $templateId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            AiParamTemplate::query()
                ->where('tenant_id', $tenantId)
                ->update(['is_default' => 0]);

            $tpl->is_default = 1;
            $tpl->save();

            // 同步租户当前启用模板
            Tenant::query()->where('id', $tenantId)->update([
                'current_ai_param_template_id' => $tpl->id,
            ]);

            return $tpl->fresh();
        });
    }

    public function delete(int $tenantId, int $templateId): void
    {
        $tpl = AiParamTemplate::query()
            ->where('id', $templateId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // 小红书账号绑定解除
        \App\Models\SocialAccount::query()
            ->where('bind_param_template_id', $tpl->id)
            ->update(['bind_param_template_id' => null]);

        // 若租户正在使用该模板 → 置空，降级平台全局
        Tenant::query()
            ->where('id', $tenantId)
            ->where('current_ai_param_template_id', $tpl->id)
            ->update(['current_ai_param_template_id' => null]);

        $wasDefault = (int) $tpl->is_default === 1;
        $tpl->delete();

        if ($wasDefault) {
            $next = AiParamTemplate::query()->where('tenant_id', $tenantId)->orderBy('id')->first();
            if ($next) {
                $next->update(['is_default' => 1]);
                // 不自动把 next 设为 current，保持「删除在用模板 → 置空降级全局」的硬性约束
            }
        }
    }
}
