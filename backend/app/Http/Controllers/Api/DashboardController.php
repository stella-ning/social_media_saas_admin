<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service)
    {
    }

    /** GET /api/dashboard/overview */
    public function overview()
    {
        return ApiResponse::success($this->service->overview());
    }

    /** GET /api/dashboard/trend */
    public function trend()
    {
        return ApiResponse::success($this->service->trendChart());
    }

    /** GET /api/dashboard/intent-pie */
    public function intentPie()
    {
        return ApiResponse::success($this->service->intentPie());
    }
}
