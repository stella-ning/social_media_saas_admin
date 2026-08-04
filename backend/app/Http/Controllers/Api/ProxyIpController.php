<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProxyImportRequest;
use App\Models\ProxyIp;
use App\Services\ProxyIpService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProxyIpController extends Controller
{
    public function __construct(private ProxyIpService $service)
    {
    }

    public function index(Request $request)
    {
        $paginator = $this->service->list($request->all());
        $paginator->getCollection()->transform(fn (ProxyIp $p) => $p->toFrontendArray());
        return ApiResponse::paginate($paginator);
    }

    public function import(ProxyImportRequest $request)
    {
        $list = $this->service->import(
            $request->input('list'),
            $request->input('location', '未知')
        );
        return ApiResponse::success(
            collect($list)->map->toFrontendArray()->values(),
            '成功导入 '.count($list).' 条代理并完成检测'
        );
    }

    public function check(ProxyIp $proxyIp)
    {
        $ip = $this->service->check($proxyIp);
        return ApiResponse::success($ip->toFrontendArray(), '检测完成');
    }

    public function bind(Request $request, ProxyIp $proxyIp)
    {
        $ip = $this->service->bindTenant($proxyIp, $request->input('tenantId'));
        return ApiResponse::success($ip->toFrontendArray(), '绑定成功');
    }

    public function destroy(ProxyIp $proxyIp)
    {
        $this->service->delete($proxyIp);
        return ApiResponse::success(null, '已移除');
    }
}
