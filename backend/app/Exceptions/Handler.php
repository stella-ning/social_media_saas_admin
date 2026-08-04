<?php

/**
 * 全局异常转统一 API JSON
 */
namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('未登录或 Token 已失效', 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $msg = collect($e->errors())->flatten()->first() ?? '参数校验失败';

                return ApiResponse::error($msg, 422, $e->errors());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $code = 500;
            if ($e instanceof HttpExceptionInterface) {
                $code = $e->getStatusCode();
            }

            $msg = config('app.debug') ? $e->getMessage() : '服务器内部错误';
            if ($code === 403) {
                $msg = $e->getMessage() ?: '无权访问';
            }
            if ($code === 404) {
                $msg = '资源不存在';
            }

            return ApiResponse::error($msg ?: '服务器内部错误', $code);
        });
    }
}
