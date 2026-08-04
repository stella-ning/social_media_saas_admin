<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\AiPromptTemplate;
use App\Models\SocialAccount;
use App\Services\AccountAiConfigService;
use App\Services\AiParamTemplateService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 租户 AI 参数模板 + 小红书账号 AI 绑定
 */
class AiParamTemplateController extends Controller
{
    use ScopesTenantData;

    public function __construct(
        private AiParamTemplateService $paramService,
        private AccountAiConfigService $accountAiService,
    ) {
    }

    /**
     * GET /api/tenant/{tenantId}/ai-param-template-list
     */
    public function list(Request $request, int $tenantId)
    {
        $tenantId = $this->assertTenantAccess($request, $tenantId);

        return ApiResponse::success([
            'list' => $this->paramService->listByTenant($tenantId),
        ]);
    }

    /**
     * GET /api/tenant/{tenant_id}/ai-template-list-by-package
     * 按套餐等级筛选可选 AI 参数模板（租户列表弹窗专用）
     */
    public function listByPackage(Request $request, int $tenant_id)
    {
        $tenantId = $this->assertTenantAccess($request, $tenant_id);

        return ApiResponse::success($this->paramService->listByPackage($tenantId));
    }

    /**
     * POST /api/tenant/save-current-ai-template
     * 保存租户当前启用的 AI 参数模板
     */
    public function saveCurrent(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['required_without:tenantId', 'nullable', 'integer'],
            'tenantId' => ['nullable', 'integer'],
            'template_id' => ['nullable', 'integer'],
            'templateId' => ['nullable', 'integer'],
            'current_ai_param_template_id' => ['nullable', 'integer'],
        ]);

        $tenantId = (int) ($data['tenant_id'] ?? $data['tenantId'] ?? 0);
        if ($scope = $this->scopeTenantId($request->user())) {
            $tenantId = $scope;
        }
        $this->assertTenantAccess($request, $tenantId);

        $templateId = $data['template_id']
            ?? $data['templateId']
            ?? $data['current_ai_param_template_id']
            ?? null;
        $templateId = $templateId !== null && $templateId !== '' ? (int) $templateId : null;

        try {
            $tenant = $this->paramService->saveCurrentTemplate($tenantId, $templateId);

            return ApiResponse::success($tenant->toFrontendArray(), '已更新租户当前 AI 参数模板');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/tenant/{tenant_id}/info
     * 返回套餐与当前启用模板名称（列表列渲染）
     */
    public function tenantInfo(Request $request, int $tenant_id)
    {
        $tenantId = $this->assertTenantAccess($request, $tenant_id);

        return ApiResponse::success($this->paramService->tenantAiInfo($tenantId));
    }

    /**
     * POST /api/tenant/ai-param-template-save
     */
    public function save(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'tenant_id' => ['nullable', 'integer'],
            'tenantId' => ['nullable', 'integer'],
            'template_name' => ['required_without:templateName', 'nullable', 'string', 'max:128'],
            'templateName' => ['nullable', 'string', 'max:128'],
            'ai_model' => ['required_without:aiModel', 'nullable', 'string', 'max:128'],
            'aiModel' => ['nullable', 'string', 'max:128'],
            'api_key' => ['nullable', 'string', 'max:512'],
            'apiKey' => ['nullable', 'string', 'max:512'],
            'api_base_url' => ['nullable', 'string', 'max:255'],
            'apiBaseUrl' => ['nullable', 'string', 'max:255'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:128000'],
            'maxTokens' => ['nullable', 'integer', 'min:1', 'max:128000'],
            'top_p' => ['nullable', 'numeric', 'between:0,1'],
            'topP' => ['nullable', 'numeric', 'between:0,1'],
            'daily_call_quota' => ['nullable', 'integer', 'min:0'],
            'dailyCallQuota' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable'],
            'isDefault' => ['nullable'],
            'template_level' => ['nullable', 'integer', 'in:1,2,3'],
            'templateLevel' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $tenantId = (int) ($data['tenant_id'] ?? $data['tenantId'] ?? 0);
        if ($scope = $this->scopeTenantId($request->user())) {
            $tenantId = $scope;
        }
        if (!$tenantId) {
            return ApiResponse::error('请指定租户', 422);
        }
        $this->assertTenantAccess($request, $tenantId);

        $payload = [
            'id' => $data['id'] ?? null,
            'tenant_id' => $tenantId,
            'template_name' => $data['template_name'] ?? $data['templateName'],
            'ai_model' => $data['ai_model'] ?? $data['aiModel'],
            'api_key' => $data['api_key'] ?? $data['apiKey'] ?? null,
            'api_base_url' => $data['api_base_url'] ?? $data['apiBaseUrl'] ?? null,
            'temperature' => $data['temperature'] ?? 0.7,
            'max_tokens' => $data['max_tokens'] ?? $data['maxTokens'] ?? 2048,
            'top_p' => $data['top_p'] ?? $data['topP'] ?? 1.0,
            'daily_call_quota' => $data['daily_call_quota'] ?? $data['dailyCallQuota'] ?? 1000,
            'is_default' => $data['is_default'] ?? $data['isDefault'] ?? false,
            'template_level' => $data['template_level'] ?? $data['templateLevel'] ?? 1,
        ];

        try {
            $tpl = $this->paramService->save($payload);

            return ApiResponse::success($tpl->toFrontendArray(), 'AI 参数模板已保存');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/tenant/ai-param-template-set-default
     */
    public function setDefault(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer'],
            'tenantId' => ['nullable', 'integer'],
            'template_id' => ['required_without:templateId', 'nullable', 'integer'],
            'templateId' => ['nullable', 'integer'],
        ]);
        $tenantId = (int) ($data['tenant_id'] ?? $data['tenantId'] ?? 0);
        if ($scope = $this->scopeTenantId($request->user())) {
            $tenantId = $scope;
        }
        $templateId = (int) ($data['template_id'] ?? $data['templateId']);
        $this->assertTenantAccess($request, $tenantId);

        $tpl = $this->paramService->setDefault($tenantId, $templateId);

        return ApiResponse::success($tpl->toFrontendArray(), '已设为租户默认模板');
    }

    /**
     * DELETE /api/tenant/ai-param-template-del
     */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer'],
            'tenantId' => ['nullable', 'integer'],
            'template_id' => ['required_without:templateId', 'nullable', 'integer'],
            'templateId' => ['nullable', 'integer'],
            'id' => ['nullable', 'integer'],
        ]);
        $tenantId = (int) ($data['tenant_id'] ?? $data['tenantId'] ?? 0);
        if ($scope = $this->scopeTenantId($request->user())) {
            $tenantId = $scope;
        }
        $templateId = (int) ($data['template_id'] ?? $data['templateId'] ?? $data['id'] ?? 0);
        $this->assertTenantAccess($request, $tenantId);

        $this->paramService->delete($tenantId, $templateId);

        return ApiResponse::success(null, '模板已删除');
    }

    /**
     * GET /api/tenant/{tenantId}/prompt-list
     */
    public function promptList(Request $request, int $tenantId)
    {
        $tenantId = $this->assertTenantAccess($request, $tenantId);
        $list = AiPromptTemplate::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AiPromptTemplate $t) => $t->toFrontendArray())
            ->values()
            ->all();

        return ApiResponse::success(['list' => $list]);
    }

    /**
     * GET /api/social-account/{id}/ai-config
     */
    public function accountAiConfig(Request $request, int $id)
    {
        $account = SocialAccount::query()->findOrFail($id);
        $this->assertAccountAccess($request, $account);
        try {
            return ApiResponse::success($this->accountAiService->getConfig($account));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/social-account/save-ai-config
     */
    public function saveAccountAiConfig(Request $request)
    {
        $data = $request->validate([
            'social_account_id' => ['required_without:id', 'nullable', 'integer'],
            'id' => ['nullable', 'integer'],
            'bind_param_template_id' => ['nullable', 'integer'],
            'bindParamTemplateId' => ['nullable', 'integer'],
            'bind_prompt_id' => ['nullable', 'integer'],
            'bindPromptId' => ['nullable', 'integer'],
            'knowledge_ids' => ['nullable', 'array'],
            'knowledgeIds' => ['nullable', 'array'],
            'reset' => ['nullable', 'boolean'],
        ]);

        $accountId = (int) ($data['social_account_id'] ?? $data['id']);
        $account = SocialAccount::query()->findOrFail($accountId);
        $this->assertAccountAccess($request, $account);

        $payload = [
            'bind_param_template_id' => $data['bind_param_template_id'] ?? $data['bindParamTemplateId'] ?? null,
            'bind_prompt_id' => $data['bind_prompt_id'] ?? $data['bindPromptId'] ?? null,
            'knowledge_ids' => $data['knowledge_ids'] ?? $data['knowledgeIds'] ?? [],
            'reset' => (bool) ($data['reset'] ?? false),
        ];

        try {
            $result = $this->accountAiService->saveConfig($account, $payload);

            return ApiResponse::success($result, !empty($payload['reset']) ? '已重置为继承租户配置' : '账号 AI 配置已保存');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    private function assertTenantAccess(Request $request, int $tenantId): int
    {
        $scope = $this->scopeTenantId($request->user());
        if ($scope && $scope !== $tenantId) {
            abort(403, '无权访问其他租户配置');
        }

        return $tenantId;
    }

    private function assertAccountAccess(Request $request, SocialAccount $account): void
    {
        $scope = $this->scopeTenantId($request->user());
        if ($scope && (int) $account->tenant_id !== $scope) {
            abort(403, '无权操作该账号');
        }
    }
}
