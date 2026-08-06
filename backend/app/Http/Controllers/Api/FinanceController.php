<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Services\FinanceService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    use ScopesTenantData;

    public function __construct(private FinanceService $service)
    {
    }

    /** 套餐目录（购买页） */
    public function catalog()
    {
        return ApiResponse::success(['list' => $this->service->catalog()]);
    }

    public function purchase(Request $request)
    {
        $data = $request->validate([
            'tenantId' => ['nullable', 'integer'],
            'tenant_id' => ['nullable', 'integer'],
            'packageCode' => ['required_without:package_code', 'nullable', 'string'],
            'package_code' => ['nullable', 'string'],
            'months' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $tenantId = $this->scopeTenantId($request->user())
            ?: (int) ($data['tenantId'] ?? $data['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return ApiResponse::error('请指定租户', 400);
        }
        try {
            $order = $this->service->purchase(
                $tenantId,
                (string) ($data['packageCode'] ?? $data['package_code']),
                (int) ($data['months'] ?? 1)
            );
            $payload = $order->toFrontendArray();
            $change = $order->getAttribute('change_type') ?: 'renew';
            $payload['changeType'] = $change;
            $payload['pausedCrawlerCount'] = (int) ($order->getAttribute('paused_crawler_count') ?? 0);
            $msg = match ($change) {
                'upgrade' => '套餐升级成功，更高权益已生效',
                'downgrade' => '套餐降级成功，超额资源已按新套餐自动收敛',
                default => '套餐续费成功，有效期已延长',
            };

            return ApiResponse::success($payload, $msg);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function orders(Request $request)
    {
        $scope = $this->scopeTenantId($request->user());
        if (!$scope) {
            $scope = (int) ($request->input('tenantId') ?? $request->input('tenant_id') ?? 0) ?: null;
        }

        return ApiResponse::success([
            'list' => $this->service->orders($scope),
        ]);
    }

    /** 超管财务报表 */
    public function overview(Request $request)
    {
        if ($this->scopeTenantId($request->user())) {
            return ApiResponse::forbidden('仅超管可查看平台财务报表');
        }

        return ApiResponse::success($this->service->overview(
            $request->input('from'),
            $request->input('to')
        ));
    }

    public function consume(Request $request)
    {
        $scope = $this->scopeTenantId($request->user());

        return ApiResponse::success([
            'list' => $this->service->consumeDetails(
                $scope,
                $request->input('from'),
                $request->input('to')
            ),
        ]);
    }

    public function premiumLogs(Request $request)
    {
        return ApiResponse::success([
            'list' => $this->service->premiumUsageLogs($this->scopeTenantId($request->user())),
        ]);
    }

    public function exportConsume(Request $request): StreamedResponse
    {
        $scope = $this->scopeTenantId($request->user());
        $csv = $this->service->exportConsumeCsv(
            $scope,
            $request->input('from'),
            $request->input('to')
        );

        return response()->streamDownload(function () use ($csv) {
            echo "\xEF\xBB\xBF".$csv;
        }, 'resource-consume-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
