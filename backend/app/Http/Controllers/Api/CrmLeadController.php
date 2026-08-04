<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Services\CrmLeadService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CrmLeadController extends Controller
{
    use ScopesTenantData;

    public function __construct(private CrmLeadService $service)
    {
    }

    public function index(Request $request)
    {
        $paginator = $this->service->list($request->all(), $this->scopeTenantId($request->user()));
        $paginator->getCollection()->transform(fn (CrmLead $l) => $l->toFrontendArray());
        return ApiResponse::paginate($paginator);
    }

    public function show(Request $request, CrmLead $crmLead)
    {
        $this->assertOwned($request, $crmLead);
        return ApiResponse::success($crmLead->load('tenant')->toFrontendArray());
    }

    public function tag(Request $request, CrmLead $crmLead)
    {
        $this->assertOwned($request, $crmLead);
        $data = $request->validate([
            'level' => ['nullable', 'in:high,mid,low'],
            'intent' => ['nullable', 'in:high,mid,low'],
            'status' => ['nullable', 'string', 'max:16'],
            'tags' => ['nullable', 'array'],
            'remark' => ['nullable', 'string'],
        ]);
        $lead = $this->service->tag($crmLead, $data);
        return ApiResponse::success($lead->toFrontendArray(), '打标已保存');
    }

    public function export(Request $request)
    {
        return $this->service->export($request->all(), $this->scopeTenantId($request->user()));
    }

    private function assertOwned(Request $request, CrmLead $lead): void
    {
        $tid = $this->scopeTenantId($request->user());
        if ($tid && $lead->tenant_id !== $tid) {
            abort(403, '无权查看该线索');
        }
    }
}
