<?php

/**
 * SocialAI SaaS API 路由
 * 前缀：/api
 * 认证：Sanctum Bearer Token
 */

use App\Http\Controllers\Api\AiConfigController;
use App\Http\Controllers\Api\AiParamTemplateController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CrawlerTaskController;
use App\Http\Controllers\Api\CrmLeadController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PackageSettingController;
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

        // 套餐权限管理（仅超管）
        Route::get('package-setting/list', [PackageSettingController::class, 'list']);
        Route::post('package-setting/save', [PackageSettingController::class, 'save']);
    });

    // 社媒账号（兼容新旧路径）
    Route::middleware('role.permission:social-accounts')->group(function () {
        // 需求接口：空闲代理
        Route::get('tenant/free-proxy-ip/{tenantId}', [SocialAccountController::class, 'freeProxyIps']);

        // 需求接口：绑定 / 会话检测
        Route::post('social-account/store', [SocialAccountController::class, 'store']);
        Route::get('social-account/check-login/{accountId}', [SocialAccountController::class, 'checkLogin']);

        // 原有 REST 风格路径（前端沿用）
        Route::prefix('social-accounts')->group(function () {
            Route::get('/', [SocialAccountController::class, 'index']);
            Route::post('/', [SocialAccountController::class, 'store']);
            Route::post('refresh', [SocialAccountController::class, 'refresh']);
            Route::post('refresh-status', [SocialAccountController::class, 'refresh']);
            Route::get('{socialAccount}/logs', [SocialAccountController::class, 'logs']);
            Route::delete('{socialAccount}', [SocialAccountController::class, 'destroy']);
        });
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
        Route::put('{proxyIp}/bind-tenant', [ProxyIpController::class, 'bind']);
        Route::delete('{proxyIp}', [ProxyIpController::class, 'destroy']);
    });

    // AI 配置（Prompt / 知识库 / AI参数模板 / 账号绑定）
    Route::middleware('role.permission:ai-config')->group(function () {
        Route::prefix('ai-config')->group(function () {
            Route::get('templates', [AiConfigController::class, 'templates']);
            Route::post('templates', [AiConfigController::class, 'saveTemplate']);
            Route::delete('templates/{template}', [AiConfigController::class, 'deleteTemplate']);
            Route::post('test', [AiConfigController::class, 'test']);
            Route::get('docs', [AiConfigController::class, 'docs']);
            Route::post('docs', [AiConfigController::class, 'uploadDoc']);
            Route::delete('docs/{doc}', [AiConfigController::class, 'deleteDoc']);
        });

        // 需求接口：租户 AI 参数模板
        Route::get('tenant/{tenantId}/ai-param-template-list', [AiParamTemplateController::class, 'list']);
        Route::post('tenant/ai-param-template-save', [AiParamTemplateController::class, 'save']);
        Route::post('tenant/ai-param-template-set-default', [AiParamTemplateController::class, 'setDefault']);
        Route::delete('tenant/ai-param-template-del', [AiParamTemplateController::class, 'destroy']);
        Route::get('tenant/{tenantId}/prompt-list', [AiParamTemplateController::class, 'promptList']);

        // 租户列表「AI配置」弹窗：按套餐筛选 / 保存当前启用 / 租户信息
        Route::get('tenant/{tenant_id}/ai-template-list-by-package', [AiParamTemplateController::class, 'listByPackage']);
        Route::post('tenant/save-current-ai-template', [AiParamTemplateController::class, 'saveCurrent']);
        Route::get('tenant/{tenant_id}/info', [AiParamTemplateController::class, 'tenantInfo']);

        // 小红书账号 AI 绑定
        Route::get('social-account/{id}/ai-config', [AiParamTemplateController::class, 'accountAiConfig']);
        Route::post('social-account/save-ai-config', [AiParamTemplateController::class, 'saveAccountAiConfig']);
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
