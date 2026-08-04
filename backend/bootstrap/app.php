<?php

use App\Http\Middleware\EnsureRolePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Support\ApiResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role.permission' => EnsureRolePermission::class,
        ]);

        // 使用 Bearer Token（auth:sanctum），不走 SPA Cookie/CSRF 流程。
        // 若开启 statefulApi()，来自 SANCTUM_STATEFUL_DOMAINS 的请求会强制校验 CSRF，
        // 导致前端 axios 登录报 “CSRF token mismatch.”
        // $middleware->statefulApi();
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // 每 6 小时检测并刷新 Cookie 会话
        $schedule->command('cookie:refresh-all')->everySixHours()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    collect($e->errors())->flatten()->first() ?: '参数校验失败',
                    422,
                    $e->errors()
                );
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::unauthorized();
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::notFound('接口不存在');
            }
        });
    })->create();
