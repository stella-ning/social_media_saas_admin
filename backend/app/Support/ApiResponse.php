<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * 统一 API 响应格式
 * { "code": 200, "msg": "操作成功", "data": {} }
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $msg = '操作成功', int $code = 200): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data ?? (object) [],
        ], $code >= 400 ? $code : 200);
    }

    public static function error(string $msg = '操作失败', int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data ?? (object) [],
        ], $code >= 400 && $code < 600 ? $code : 400);
    }

    public static function unauthorized(string $msg = '未登录或登录已过期'): JsonResponse
    {
        return self::error($msg, 401);
    }

    public static function forbidden(string $msg = '无权访问'): JsonResponse
    {
        return self::error($msg, 403);
    }

    public static function notFound(string $msg = '资源不存在'): JsonResponse
    {
        return self::error($msg, 404);
    }

    /**
     * 分页列表统一结构（对齐前端）
     */
    public static function paginate($paginator, string $msg = '操作成功'): JsonResponse
    {
        return self::success([
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'size' => $paginator->perPage(),
        ], $msg);
    }
}
