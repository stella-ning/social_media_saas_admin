<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrawlerTaskStoreRequest;
use App\Models\CrawlerTask;
use App\Services\CrawlerTaskService;
use App\Support\ApiResponse;
use App\Support\CrawlerCommentBridge;
use Illuminate\Http\Request;
use RuntimeException;

class CrawlerTaskController extends Controller
{
    use ScopesTenantData;

    public function __construct(
        private CrawlerTaskService $service,
        private CrawlerCommentBridge $commentBridge,
    ) {
    }

    public function index(Request $request)
    {
        $paginator = $this->service->list($request->all(), $this->scopeTenantId($request->user()));
        $paginator->getCollection()->transform(fn (CrawlerTask $t) => $t->toFrontendArray());
        return ApiResponse::paginate($paginator);
    }

    /**
     * GET /api/crawler-tasks/executable-accounts?tenant_id=
     * 状态正常 + 套餐允许平台 + 已绑代理的社媒账号
     */
    public function executableAccounts(Request $request)
    {
        $tenantId = $this->scopeTenantId($request->user());
        if (!$tenantId) {
            $tenantId = (int) ($request->input('tenant_id') ?? $request->input('tenantId') ?? 0);
        }
        if ($tenantId <= 0) {
            return ApiResponse::error('请指定租户', 400);
        }
        try {
            $data = $this->service->executableAccounts($tenantId);

            return ApiResponse::success($data);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function store(CrawlerTaskStoreRequest $request)
    {
        $data = $request->validated();
        if ($tid = $this->scopeTenantId($request->user())) {
            $data['tenantId'] = $tid;
        }
        try {
            $task = $this->service->create($data);
            return ApiResponse::success($task->toFrontendArray(), '爬虫任务已创建并启动');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function update(Request $request, CrawlerTask $crawlerTask)
    {
        $this->assertOwned($request, $crawlerTask);
        try {
            $task = $this->service->update($crawlerTask, $request->all());
            return ApiResponse::success($task->toFrontendArray(), '任务已更新');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function toggle(Request $request, CrawlerTask $crawlerTask)
    {
        $this->assertOwned($request, $crawlerTask);
        try {
            $task = $this->service->toggle($crawlerTask);
            $msg = $task->status === 'running' ? '任务已启动' : '任务已停止';
            return ApiResponse::success($task->toFrontendArray(), $msg);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function logs(Request $request, CrawlerTask $crawlerTask)
    {
        $this->assertOwned($request, $crawlerTask);

        return ApiResponse::success(['list' => $this->service->logs($crawlerTask)]);
    }

    /**
     * POST /api/crawler-tasks/{id}/collect-callback
     * 爬虫 Worker 回调：提交评论区采集结果 → 咨询留言接入会话
     */
    public function collectCallback(Request $request, CrawlerTask $crawlerTask)
    {
        $this->assertOwned($request, $crawlerTask);
        $data = $request->validate([
            'comments' => ['required', 'array', 'min:1'],
            'comments.*.content' => ['required', 'string'],
            'comments.*.name' => ['nullable', 'string', 'max:64'],
            'comments.*.nickname' => ['nullable', 'string', 'max:64'],
            'comments.*.avatar' => ['nullable', 'string', 'max:512'],
        ]);

        try {
            $result = $this->commentBridge->handleCollectCallback($crawlerTask, $data['comments']);

            return ApiResponse::success($result, sprintf(
                '采集接入完成：咨询留言 %d 条已进入消息会话',
                $result['inquiry']
            ));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/crawler-tasks/{id}/simulate-collect
     * 演示：模拟采集同行评论区并识别咨询留言 → 消息会话
     */
    public function simulateCollect(Request $request, CrawlerTask $crawlerTask)
    {
        $this->assertOwned($request, $crawlerTask);
        try {
            $result = $this->commentBridge->simulateCollect($crawlerTask);

            return ApiResponse::success($result, sprintf(
                '已模拟采集并接入 %d 条咨询留言，请到「消息会话」查看',
                $result['inquiry']
            ));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    private function assertOwned(Request $request, CrawlerTask $task): void
    {
        $tid = $this->scopeTenantId($request->user());
        if ($tid && $task->tenant_id !== $tid) {
            abort(403, '无权操作该任务');
        }
    }
}
