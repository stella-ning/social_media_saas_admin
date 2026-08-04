<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\MessageSession;
use App\Services\MessageService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    use ScopesTenantData;

    public function __construct(private MessageService $service)
    {
    }

    public function index(Request $request)
    {
        $list = $this->service->sessions($request->all(), $this->scopeTenantId($request->user()));
        return ApiResponse::success($list->values());
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
        $result = $this->service->send($session, $data['content'], $data['from'] ?? 'human');
        return ApiResponse::success($result, '发送成功');
    }

    public function aiSwitch(Request $request, MessageSession $session)
    {
        $this->assertOwned($request, $session);
        $enabled = (bool) $request->input('aiAutoReply', true);
        $session = $this->service->updateAiSwitch($session, $enabled);
        return ApiResponse::success([
            'id' => $session->id,
            'aiAutoReply' => $session->ai_auto_reply,
        ], '已更新');
    }

    private function assertOwned(Request $request, MessageSession $session): void
    {
        $tid = $this->scopeTenantId($request->user());
        if ($tid && $session->tenant_id !== $tid) {
            abort(403, '无权访问该会话');
        }
    }
}
