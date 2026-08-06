<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\MessageSession;
use App\Models\QuickReply;
use App\Services\MessageService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 消息会话管理
 */
class MessageController extends Controller
{
    use ScopesTenantData;

    public function __construct(private MessageService $service)
    {
    }

    public function index(Request $request)
    {
        $list = $this->service->sessions($request->all(), $this->scopeTenantId($request->user()));

        return ApiResponse::success(['list' => $list->values()]);
    }

    public function show(Request $request, MessageSession $session)
    {
        $this->assertOwned($request, $session);

        return ApiResponse::success($this->service->detail($session));
    }

    public function send(Request $request, MessageSession $session)
    {
        $this->assertOwned($request, $session);
        $data = $request->validate([
            'content' => ['required', 'string'],
            'from' => ['nullable', 'in:human,ai,user'],
        ]);
        try {
            $result = $this->service->send($session, $data['content'], $data['from'] ?? 'human');

            return ApiResponse::success($result, '发送成功');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /** PUT settings / ai-switch 兼容 */
    public function updateSettings(Request $request, MessageSession $session)
    {
        $this->assertOwned($request, $session);
        $session = $this->service->updateSettings($session, $request->all());

        return ApiResponse::success($session->toFrontendArray(), '设置已保存');
    }

    public function aiSwitch(Request $request, MessageSession $session)
    {
        return $this->updateSettings($request, $session);
    }

    /** 高意向一键推 CRM */
    public function pushCrm(Request $request, MessageSession $session)
    {
        $this->assertOwned($request, $session);
        try {
            $lead = $this->service->pushToCrm($session);

            return ApiResponse::success([
                'lead' => $lead->toFrontendArray(),
                'session' => $session->fresh(['tenant', 'socialAccount'])->toFrontendArray(),
            ], '已推送至 CRM 客户线索库');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /** 模拟接收访客消息（演示/爬虫回调） */
    public function ingest(Request $request)
    {
        $data = $request->validate([
            'tenantId' => ['required_without:tenant_id', 'nullable', 'integer'],
            'tenant_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:64'],
            'platform' => ['required', 'string', 'max:32'],
            'content' => ['required', 'string'],
            'socialAccountId' => ['nullable', 'integer'],
            'social_account_id' => ['nullable', 'integer'],
            'avatar' => ['nullable', 'string'],
            'session_id' => ['nullable', 'integer'],
        ]);
        if ($tid = $this->scopeTenantId($request->user())) {
            $data['tenantId'] = $tid;
        }
        try {
            $session = $this->service->ingestVisitorMessage($data);

            return ApiResponse::success($session->toFrontendArray(), '访客消息已接入并触发自动接待');
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function quickReplies(Request $request)
    {
        $tenantId = $this->scopeTenantId($request->user())
            ?: (int) ($request->input('tenantId') ?? $request->input('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return ApiResponse::error('请指定租户', 400);
        }

        return ApiResponse::success(['list' => $this->service->quickReplies($tenantId)]);
    }

    public function saveQuickReply(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'tenantId' => ['required_without:tenant_id', 'nullable', 'integer'],
            'tenant_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:64'],
            'content' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:32'],
            'sort' => ['nullable', 'integer'],
        ]);
        if ($tid = $this->scopeTenantId($request->user())) {
            $data['tenantId'] = $tid;
        }
        $row = $this->service->saveQuickReply($data);

        return ApiResponse::success($row->toFrontendArray(), '快捷话术已保存');
    }

    public function deleteQuickReply(Request $request, QuickReply $quickReply)
    {
        $tid = $this->scopeTenantId($request->user());
        if ($tid && (int) $quickReply->tenant_id !== $tid) {
            abort(403, '无权删除');
        }
        $this->service->deleteQuickReply($quickReply);

        return ApiResponse::success(null, '已删除');
    }

    public function alertLogs(Request $request)
    {
        $tid = $this->scopeTenantId($request->user());
        $q = \App\Models\MessageAlertLog::query()->orderByDesc('id')->limit(50);
        if ($tid) {
            $q->where('tenant_id', $tid);
        } elseif ($request->filled('tenantId')) {
            $q->where('tenant_id', (int) $request->input('tenantId'));
        }

        return ApiResponse::success([
            'list' => $q->get()->map->toFrontendArray()->values()->all(),
        ]);
    }

    private function assertOwned(Request $request, MessageSession $session): void
    {
        $tid = $this->scopeTenantId($request->user());
        if ($tid && $session->tenant_id !== $tid) {
            abort(403, '无权访问该会话');
        }
    }
}
