<?php

/**
 * 统一 API 响应结构
 * { code, msg, data }
 */
namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * 成功响应
     */
    public static function success(mixed $data = null, string $msg = '操作成功', int $code = 200): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ], $code >= 400 ? $code : 200);
    }

    /**
     * 失败响应
     */
    public static function error(string $msg = '操作失败', int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ], $code >= 400 && $code < 600 ? $code : 400);
    }
}
