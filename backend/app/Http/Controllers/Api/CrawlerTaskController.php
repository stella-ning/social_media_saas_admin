<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrawlerTaskStoreRequest;
use App\Models\CrawlerTask;
use App\Services\CrawlerTaskService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CrawlerTaskController extends Controller
{
    use ScopesTenantData;

    public function __construct(private CrawlerTaskService $service)
    {
    }

    public function index(Request $request)
    {
        $paginator = $this->service->list($request->all(), $this->scopeTenantId($request->user()));
        $paginator->getCollection()->transform(fn (CrawlerTask $t) => $t->toFrontendArray());
        return ApiResponse::paginate($paginator);
    }

    public function store(CrawlerTaskStoreRequest $request)
    {
        $data = $request->validated();
        if ($tid = $this->scopeTenantId($request->user())) {
            $data['tenantId'] = $tid;
        }
        $task = $this->service->create($data);
        return ApiResponse::success($task->toFrontendArray(), '爬虫任务已创建并启动');
    }

    public function update(Request $request, CrawlerTask $crawlerTask)
    {
        $this->assertOwned($request, $crawlerTask);
        $task = $this->service->update($crawlerTask, $request->all());
        return ApiResponse::success($task->toFrontendArray(), '任务已更新');
    }

    public function toggle(Request $request, CrawlerTask $crawlerTask)
    {
        $this->assertOwned($request, $crawlerTask);
        $task = $this->service->toggle($crawlerTask);
        $msg = $task->status === 'running' ? '任务已启动' : '任务已停止';
        return ApiResponse::success($task->toFrontendArray(), $msg);
    }

    public function logs(Request $request, CrawlerTask $crawlerTask)
    {
        $this->assertOwned($request, $crawlerTask);
        return ApiResponse::success($this->service->logs($crawlerTask));
    }

    private function assertOwned(Request $request, CrawlerTask $task): void
    {
        $tid = $this->scopeTenantId($request->user());
        if ($tid && $task->tenant_id !== $tid) {
            abort(403, '无权操作该任务');
        }
    }
}
