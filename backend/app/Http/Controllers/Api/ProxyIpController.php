<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProxyImportRequest;
use App\Models\ProxyIp;
use App\Services\ProxyIpService;
use App\Support\ApiResponse;
use App\Support\PackageQuota;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 代理 IP：平台公共住宅代理池
 * - 超管可导入公共池 / 企业专属隔离池
 * - 全局拦截租户自有代理上传
 * - 租户仅可查看已分配 IP、检测、访问日志
 */
class ProxyIpController extends Controller
{
    use ScopesTenantData;

    public function __construct(private ProxyIpService $service)
    {
    }

    public function index(Request $request)
    {
        $scope = $this->scopeTenantId($request->user());
        $paginator = $this->service->list($request->all(), $scope);
        $paginator->getCollection()->transform(fn (ProxyIp $p) => $p->toFrontendArray());

        return ApiResponse::paginate($paginator);
    }

    public function import(ProxyImportRequest $request)
    {
        // 租户账号禁止导入
        if ($this->scopeTenantId($request->user())) {
            return ApiResponse::error('平台已关闭租户自有代理上传，请使用已分配的公共住宅代理', 403);
        }

        $tenantId = $request->input('tenantId') ?? $request->input('tenant_id');
        $tenantId = $tenantId ? (int) $tenantId : null;
        $poolType = (string) ($request->input('poolType') ?? $request->input('pool_type') ?? 'public');

        // 带 tenantId 且非 dedicated = 旧「自有代理」语义 → 强制拦截
        if ($tenantId && $poolType !== 'dedicated') {
            return ApiResponse::error(
                '平台已关闭租户自有代理上传：请导入「平台公共池」或企业版「专属隔离池」',
                400
            );
        }

        try {
            $list = $this->service->import(
                $request->input('list'),
                $request->input('location', '未知'),
                $tenantId,
                $poolType
            );
            $msg = $poolType === 'dedicated'
                ? '成功导入 '.count($list).' 条专属隔离池代理'
                : '成功导入 '.count($list).' 条平台公共住宅代理';

            return ApiResponse::success(
                collect($list)->map->toFrontendArray()->values(),
                $msg
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function check(Request $request, ProxyIp $proxyIp)
    {
        try {
            $ip = $this->service->check($proxyIp, $this->scopeTenantId($request->user()));

            return ApiResponse::success($ip->toFrontendArray(), '检测完成');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function batchRiskCheck(Request $request)
    {
        $tenantId = $this->scopeTenantId($request->user())
            ?: (int) ($request->input('tenantId') ?? $request->input('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return ApiResponse::error('请指定租户', 400);
        }
        try {
            return ApiResponse::success(
                ['list' => $this->service->batchRiskCheck($tenantId)],
                '批量风险检测完成'
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function accessLogs(Request $request, ProxyIp $proxyIp)
    {
        try {
            return ApiResponse::success([
                'list' => $this->service->accessLogs($proxyIp, $this->scopeTenantId($request->user())),
            ]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function allocated(Request $request)
    {
        $tenantId = $this->scopeTenantId($request->user())
            ?: (int) ($request->input('tenantId') ?? $request->input('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return ApiResponse::error('请指定租户', 400);
        }

        return ApiResponse::success($this->service->allocatedForTenant($tenantId));
    }

    public function bind(Request $request, ProxyIp $proxyIp)
    {
        if ($this->scopeTenantId($request->user())) {
            return ApiResponse::error('租户不可手动绑定外部代理，系统将自动从公共池分配', 403);
        }
        try {
            $tenantId = $request->input('tenantId') ?? $request->input('tenant_id');
            $ip = $this->service->bindTenant(
                $proxyIp,
                $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null
            );

            return ApiResponse::success($ip->toFrontendArray(), '分配成功');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, ProxyIp $proxyIp)
    {
        if ($this->scopeTenantId($request->user())) {
            return ApiResponse::forbidden('租户不可移除平台代理资源');
        }
        $this->service->destroy($proxyIp);

        return ApiResponse::success(null, '已移除');
    }

    public function tenantQuota(Request $request, int $tenantId)
    {
        $scope = $this->scopeTenantId($request->user());
        if ($scope && $scope !== $tenantId) {
            return ApiResponse::forbidden();
        }
        try {
            return ApiResponse::success(PackageQuota::quotaBriefForTenant($tenantId));
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
