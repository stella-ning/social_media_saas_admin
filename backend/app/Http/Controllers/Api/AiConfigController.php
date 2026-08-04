<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\AiPromptTemplate;
use App\Models\KnowledgeDoc;
use App\Models\Tenant;
use App\Services\AiConfigService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AiConfigController extends Controller
{
    use ScopesTenantData;

    public function __construct(private AiConfigService $service)
    {
    }

    public function templates(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        return ApiResponse::success($this->service->templates($tenantId));
    }

    public function saveTemplate(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:128'],
            'category' => ['required', 'string', 'max:64'],
            'role' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'tagType' => ['nullable', 'string'],
            'tenantId' => ['nullable', 'integer'],
        ]);
        $data['tenantId'] = $this->resolveTenantId($request, $data['tenantId'] ?? null);
        try {
            $tpl = $this->service->saveTemplate($data);
            return ApiResponse::success($tpl->toFrontendArray(), '模板已保存');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function deleteTemplate(AiPromptTemplate $template)
    {
        $this->service->deleteTemplate($template);
        return ApiResponse::success(null, '已删除');
    }

    public function test(Request $request)
    {
        $request->validate(['input' => ['required', 'string']]);
        $result = $this->service->testPreview($request->input('input'));
        return ApiResponse::success(['result' => $result], '生成完成');
    }

    public function docs(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        return ApiResponse::success($this->service->docs($tenantId));
    }

    public function uploadDoc(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'tenantId' => ['nullable', 'integer'],
        ]);
        $data['tenantId'] = $this->resolveTenantId($request, $data['tenantId'] ?? null);
        try {
            $doc = $this->service->uploadDoc($data);
            return ApiResponse::success($doc->toFrontendArray(), '文档上传成功');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function deleteDoc(KnowledgeDoc $doc)
    {
        $this->service->deleteDoc($doc);
        return ApiResponse::success(null, '已删除');
    }

    private function resolveTenantId(Request $request, ?int $input = null): int
    {
        if ($tid = $this->scopeTenantId($request->user())) {
            return $tid;
        }
        if ($input) {
            return $input;
        }
        if ($request->filled('tenantId')) {
            return (int) $request->input('tenantId');
        }
        if ($request->filled('tenant_id')) {
            return (int) $request->input('tenant_id');
        }
        // 默认取第一个租户
        return (int) Tenant::query()->value('id');
    }
}
