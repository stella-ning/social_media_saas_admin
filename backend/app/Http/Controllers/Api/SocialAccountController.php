<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SocialAccountStoreRequest;
use App\Models\SocialAccount;
use App\Services\SocialAccountService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

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

    public function store(SocialAccountStoreRequest $request)
    {
        $data = $request->validated();
        if ($tid = $this->scopeTenantId($request->user())) {
            $data['tenantId'] = $tid;
        }
        $account = $this->service->create($data);
        return ApiResponse::success($account->toFrontendArray(), '账号验证绑定成功');
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
        if ($tid && $account->tenant_id !== $tid) {
            abort(403, '无权操作该账号');
        }
    }
}
