<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SocialAccountStoreRequest;
use App\Models\SocialAccount;
use App\Services\SocialAccountService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 社媒账号管理
 * - POST store：凭据 + 专属代理 → Python 自动登录抓 Cookie
 * - GET free-proxy / check-login / logs
 */
class SocialAccountController extends Controller
{
    use ScopesTenantData;

    public function __construct(private SocialAccountService $service)
    {
    }

    public function index(Request $request)
    {
        $paginator = $this->service->list($request->all(), $this->scopeTenantId($request->user()));
        $paginator->getCollection()->transform(fn (SocialAccount $a) => $a->toFrontendArray());

        return ApiResponse::paginate($paginator);
    }

    /**
     * POST /api/social-account/store  （同时兼容 /api/social-accounts）
     */
    public function store(SocialAccountStoreRequest $request)
    {
        $data = $request->validated();
        if ($tid = $this->scopeTenantId($request->user())) {
            $data['tenant_id'] = $tid;
        }

        try {
            $account = $this->service->storeWithAutoLogin($data);

            return ApiResponse::success($account->toFrontendArray(), '账号验证绑定成功');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return ApiResponse::error('绑定失败，请稍后重试', 500);
        }
    }

    /**
     * GET /api/tenant/free-proxy-ip/{tenantId}
     */
    public function freeProxyIps(Request $request, int $tenantId)
    {
        $scope = $this->scopeTenantId($request->user());
        if ($scope && $scope !== $tenantId) {
            return ApiResponse::forbidden('无权查看其他租户的代理资源');
        }

        $list = $this->service->freeProxyIps($tenantId);

        return ApiResponse::success(['list' => $list]);
    }

    /**
     * GET /api/social-account/check-login/{accountId}
     */
    public function checkLogin(Request $request, int $accountId)
    {
        $account = SocialAccount::query()->findOrFail($accountId);
        $this->assertOwned($request, $account);
        $result = $this->service->checkLogin($account);

        return ApiResponse::success($result, $result['msg'] ?? '检测完成');
    }

    public function logs(Request $request, SocialAccount $socialAccount)
    {
        $this->assertOwned($request, $socialAccount);

        return ApiResponse::success([
            'list' => $this->service->operationLogs($socialAccount),
        ]);
    }

    public function destroy(Request $request, SocialAccount $socialAccount)
    {
        $this->assertOwned($request, $socialAccount);
        $this->service->unbind($socialAccount);

        return ApiResponse::success(null, '已解绑');
    }

    public function refresh(Request $request)
    {
        $list = $this->service->refreshStatus($this->scopeTenantId($request->user()));

        return ApiResponse::success($list, '账号状态已刷新');
    }

    private function assertOwned(Request $request, SocialAccount $account): void
    {
        $tid = $this->scopeTenantId($request->user());
        if ($tid && (int) $account->tenant_id !== $tid) {
            abort(403, '无权操作该账号');
        }
    }
}
