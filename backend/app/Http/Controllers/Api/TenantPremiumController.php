<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Services\TenantPremiumService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TenantPremiumController extends Controller
{
    use ScopesTenantData;

    public function __construct(private TenantPremiumService $service)
    {
    }

    private function tenantId(Request $request): int
    {
        $tid = $this->scopeTenantId($request->user())
            ?: (int) ($request->input('tenantId') ?? $request->input('tenant_id') ?? 0);
        if ($tid <= 0) {
            abort(response()->json(['code' => 400, 'msg' => '请指定租户', 'data' => (object) []], 400));
        }

        return $tid;
    }

    public function subAccounts(Request $request)
    {
        try {
            return ApiResponse::success(['list' => $this->service->listSubAccounts($this->tenantId($request))]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function saveSubAccount(Request $request)
    {
        try {
            $row = $this->service->saveSubAccount($this->tenantId($request), $request->all());

            return ApiResponse::success($row->toFrontendArray(), '子账号已保存');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function deleteSubAccount(Request $request, int $id)
    {
        try {
            $this->service->deleteSubAccount($this->tenantId($request), $id);

            return ApiResponse::success(null, '已删除');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function industryPrompts(Request $request)
    {
        try {
            return ApiResponse::success(['list' => $this->service->industryPrompts($this->tenantId($request))]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function humanBehavior(Request $request)
    {
        try {
            $tid = $this->tenantId($request);
            $taskId = $request->input('crawlerTaskId') ?? $request->input('crawler_task_id');

            return ApiResponse::success(
                $this->service->getHumanBehavior($tid, $taskId ? (int) $taskId : null) ?: (object) []
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function saveHumanBehavior(Request $request)
    {
        try {
            $row = $this->service->saveHumanBehavior($this->tenantId($request), $request->all());

            return ApiResponse::success($row->toFrontendArray(), '真人行为参数已保存');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function crmReminders(Request $request)
    {
        try {
            return ApiResponse::success(['list' => $this->service->listCrmReminders($this->tenantId($request))]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function saveCrmReminder(Request $request)
    {
        try {
            $row = $this->service->saveCrmReminder($this->tenantId($request), $request->all());

            return ApiResponse::success($row->toFrontendArray(), '跟进提醒已创建');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function completeCrmReminder(Request $request, int $id)
    {
        try {
            $this->service->completeCrmReminder($this->tenantId($request), $id);

            return ApiResponse::success(null, '已完成');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function updateIpFlags(Request $request)
    {
        try {
            $tenant = $this->service->updateIpPoolFlags($this->tenantId($request), $request->all());

            return ApiResponse::success($tenant->toFrontendArray(), 'IP 池策略已更新');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
