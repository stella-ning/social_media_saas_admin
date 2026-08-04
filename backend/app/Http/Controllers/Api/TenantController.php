<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantPackageRequest;
use App\Http\Requests\TenantStoreRequest;
use App\Models\Tenant;
use App\Services\TenantService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private TenantService $service)
    {
    }

    /** GET /api/tenants */
    public function index(Request $request)
    {
        $paginator = $this->service->list($request->all());
        $paginator->getCollection()->transform(fn (Tenant $t) => $t->toFrontendArray());
        return ApiResponse::paginate($paginator);
    }

    /** GET /api/tenants/stats */
    public function stats()
    {
        return ApiResponse::success($this->service->stats());
    }

    /** GET /api/tenants/{id} */
    public function show(Tenant $tenant)
    {
        return ApiResponse::success($tenant->toFrontendArray());
    }

    /** POST /api/tenants */
    public function store(TenantStoreRequest $request)
    {
        $tenant = $this->service->create($request->validated());
        return ApiResponse::success($tenant->toFrontendArray(), '租户创建成功');
    }

    /** PUT /api/tenants/{id} */
    public function update(TenantStoreRequest $request, Tenant $tenant)
    {
        $tenant = $this->service->update($tenant, $request->validated());
        return ApiResponse::success($tenant->toFrontendArray(), '修改已保存');
    }

    /** PUT /api/tenants/{id}/package */
    public function package(TenantPackageRequest $request, Tenant $tenant)
    {
        $tenant = $this->service->updatePackage($tenant, $request->validated());
        return ApiResponse::success($tenant->toFrontendArray(), '配置已更新');
    }

    /** PUT /api/tenants/{id}/status */
    public function status(Request $request, Tenant $tenant)
    {
        $status = (int) $request->input('status', 1);
        $tenant = $this->service->toggleStatus($tenant, $status);
        return ApiResponse::success($tenant->toFrontendArray(), $status ? '已启用' : '已禁用');
    }

    /** DELETE /api/tenants/{id} */
    public function destroy(Tenant $tenant)
    {
        $this->service->delete($tenant);
        return ApiResponse::success(null, '已删除');
    }

    /** GET /api/tenants/export */
    public function export(Request $request)
    {
        return $this->service->exportCsv($request->all());
    }

    /** GET /api/tenants/{id}/config 缓存配置 */
    public function config(int $id)
    {
        return ApiResponse::success($this->service->getCachedConfig($id));
    }
}
