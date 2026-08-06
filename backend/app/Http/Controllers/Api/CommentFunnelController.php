<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\SensitiveWord;
use App\Services\CommentFunnelService;
use App\Services\SensitiveWordService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CommentFunnelController extends Controller
{
    use ScopesTenantData;

    public function __construct(
        private CommentFunnelService $funnel,
        private SensitiveWordService $words
    ) {
    }

    /** GET /api/comment-funnel/records */
    public function records(Request $request)
    {
        $scope = $this->scopeTenantId($request->user());
        $page = $this->funnel->listRecords($request->all(), $scope);

        return ApiResponse::success([
            'list' => collect($page->items())->map->toFrontendArray()->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'size' => $page->perPage(),
        ]);
    }

    /** GET /api/comment-funnel/stats */
    public function stats(Request $request)
    {
        $scope = $this->scopeTenantId($request->user());
        $tenantId = $scope ?: ($request->integer('tenantId') ?: $request->integer('tenant_id') ?: null);
        $taskId = $request->integer('taskId') ?: $request->integer('task_id') ?: null;

        return ApiResponse::success($this->funnel->funnelStats($tenantId ?: null, $taskId ?: null));
    }

    /** GET /api/comment-funnel/blacklist */
    public function blacklist(Request $request)
    {
        $scope = $this->scopeTenantId($request->user());
        $page = $this->funnel->listBlacklist($request->all(), $scope);

        return ApiResponse::success([
            'list' => collect($page->items())->map->toFrontendArray()->values(),
            'total' => $page->total(),
        ]);
    }

    /** GET /api/sensitive-words */
    public function wordList(Request $request)
    {
        $user = $request->user();
        $scope = $this->scopeTenantId($user);
        $page = $this->words->list($request->all(), $scope);

        return ApiResponse::success([
            'list' => collect($page->items())->map->toFrontendArray()->values(),
            'total' => $page->total(),
        ]);
    }

    /** POST /api/sensitive-words */
    public function wordSave(Request $request)
    {
        $user = $request->user();
        try {
            $row = $this->words->create(
                $request->all(),
                $this->scopeTenantId($user),
                $user->isSuperAdmin()
            );

            return ApiResponse::success($row->toFrontendArray(), '已保存');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /** DELETE /api/sensitive-words/{id} */
    public function wordDelete(Request $request, int $id)
    {
        $user = $request->user();
        $word = SensitiveWord::query()->findOrFail($id);
        try {
            $this->words->delete($word, $this->scopeTenantId($user), $user->isSuperAdmin());

            return ApiResponse::success(null, '已删除');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }
    }
}
