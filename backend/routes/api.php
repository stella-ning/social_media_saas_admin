<?php

/**
 * SocialAI SaaS API 路由
 * 前缀：/api
 * 认证：Sanctum Bearer Token
 */

use App\Http\Controllers\Api\AiConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CrawlerTaskController;
use App\Http\Controllers\Api\CrmLeadController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProxyIpController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SocialAccountController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

// ========== 公开接口 ==========
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ========== 需登录 ==========
Route::middleware('auth:sanctum')->group(function () {

    // 鉴权
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('switch-role', [AuthController::class, 'switchRole']);
    });

    // 仪表盘
    Route::middleware('role.permission:dashboard')->prefix('dashboard')->group(function () {
        Route::get('overview', [DashboardController::class, 'overview']);
        Route::get('trend', [DashboardController::class, 'trend']);
        Route::get('intent-pie', [DashboardController::class, 'intentPie']);
    });

    // 租户管理（仅超管）
    Route::middleware('role.permission:tenants')->group(function () {
        Route::get('tenants/stats', [TenantController::class, 'stats']);
        Route::get('tenants/export', [TenantController::class, 'export']);
        Route::get('tenants/{id}/config', [TenantController::class, 'config']);
        Route::put('tenants/{tenant}/package', [TenantController::class, 'package']);
        Route::put('tenants/{tenant}/status', [TenantController::class, 'status']);
        Route::apiResource('tenants', TenantController::class);
    });

    // 社媒账号
    Route::middleware('role.permission:social-accounts')->prefix('social-accounts')->group(function () {
        Route::get('/', [SocialAccountController::class, 'index']);
        Route::post('/', [SocialAccountController::class, 'store']);
        Route::post('refresh', [SocialAccountController::class, 'refresh']);
        Route::delete('{socialAccount}', [SocialAccountController::class, 'destroy']);
    });

    // 爬虫任务
    Route::middleware('role.permission:crawler-tasks')->prefix('crawler-tasks')->group(function () {
        Route::get('/', [CrawlerTaskController::class, 'index']);
        Route::post('/', [CrawlerTaskController::class, 'store']);
        Route::put('{crawlerTask}', [CrawlerTaskController::class, 'update']);
        Route::post('{crawlerTask}/toggle', [CrawlerTaskController::class, 'toggle']);
        Route::get('{crawlerTask}/logs', [CrawlerTaskController::class, 'logs']);
    });

    // 代理 IP（仅超管）
    Route::middleware('role.permission:proxy-ips')->prefix('proxy-ips')->group(function () {
        Route::get('/', [ProxyIpController::class, 'index']);
        Route::post('import', [ProxyIpController::class, 'import']);
        Route::post('{proxyIp}/check', [ProxyIpController::class, 'check']);
        Route::put('{proxyIp}/bind', [ProxyIpController::class, 'bind']);
        Route::delete('{proxyIp}', [ProxyIpController::class, 'destroy']);
    });

    // AI 配置
    Route::middleware('role.permission:ai-config')->prefix('ai-config')->group(function () {
        Route::get('templates', [AiConfigController::class, 'templates']);
        Route::post('templates', [AiConfigController::class, 'saveTemplate']);
        Route::delete('templates/{template}', [AiConfigController::class, 'deleteTemplate']);
        Route::post('test', [AiConfigController::class, 'test']);
        Route::get('docs', [AiConfigController::class, 'docs']);
        Route::post('docs', [AiConfigController::class, 'uploadDoc']);
        Route::delete('docs/{doc}', [AiConfigController::class, 'deleteDoc']);
    });

    // CRM 线索
    Route::middleware('role.permission:crm-leads')->prefix('crm-leads')->group(function () {
        Route::get('/', [CrmLeadController::class, 'index']);
        Route::get('export', [CrmLeadController::class, 'export']);
        Route::get('{crmLead}', [CrmLeadController::class, 'show']);
        Route::post('{crmLead}/tag', [CrmLeadController::class, 'tag']);
    });

    // 消息会话
    Route::middleware('role.permission:messages')->prefix('messages')->group(function () {
        Route::get('sessions', [MessageController::class, 'index']);
        Route::get('sessions/{session}', [MessageController::class, 'show']);
        Route::post('sessions/{session}/send', [MessageController::class, 'send']);
        Route::put('sessions/{session}/ai-switch', [MessageController::class, 'aiSwitch']);
    });

    // 系统设置（仅超管）
    Route::middleware('role.permission:settings')->prefix('settings')->group(function () {
        Route::get('basic', [SettingController::class, 'basic']);
        Route::put('basic', [SettingController::class, 'saveBasic']);
        Route::get('security', [SettingController::class, 'security']);
        Route::put('security', [SettingController::class, 'saveSecurity']);
        Route::get('users', [SettingController::class, 'users']);
        Route::post('users', [SettingController::class, 'storeUser']);
        Route::put('users/{user}', [SettingController::class, 'updateUser']);
        Route::post('users/{user}/toggle', [SettingController::class, 'toggleUser']);
    });
});
