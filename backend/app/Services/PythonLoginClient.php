<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 调用 Python-FastAPI + Playwright 自动登录服务
 * 禁止在日志中输出 account / password / cookie 明文
 */
class PythonLoginClient
{
    /**
     * 发起自动登录
     *
     * @param  array{
     *   platform:string,
     *   proxy_server_addr:string,
     *   account:string,
     *   password:string,
     *   verify_code?:string|null,
     *   user_agent:string
     * }  $payload
     * @return array{success:bool,code:int,msg:string,cookies?:array,user_agent?:string,captcha?:bool}
     */
    public function autoLogin(array $payload): array
    {
        $base = rtrim((string) config('services.python_login.base_url'), '/');
        $path = (string) config('services.python_login.auto_login_path', '/api/auto-login');
        $timeout = (int) config('services.python_login.timeout', 120);

        Log::info('python_login.request', [
            'platform' => $payload['platform'] ?? null,
            'proxy' => $payload['proxy_server_addr'] ?? null,
            // 仅记录账号哈希，避免泄露明文
            'account_hash' => isset($payload['account']) ? substr(hash('sha256', $payload['account']), 0, 12) : null,
            'has_verify_code' => !empty($payload['verify_code']),
        ]);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($base.$path, [
                    'platform' => $payload['platform'],
                    'proxy_server_addr' => $payload['proxy_server_addr'],
                    'account' => $payload['account'],
                    'password' => $payload['password'],
                    'verify_code' => $payload['verify_code'] ?? null,
                    'user_agent' => $payload['user_agent'],
                ]);
        } catch (\Throwable $e) {
            Log::warning('python_login.http_error', ['error' => $e->getMessage()]);
            throw new RuntimeException('登录服务不可用，请稍后重试');
        }

        $json = $response->json() ?? [];
        $ok = $response->successful() && (($json['code'] ?? 0) === 200 || ($json['success'] ?? false) === true);

        Log::info('python_login.response', [
            'http_status' => $response->status(),
            'code' => $json['code'] ?? null,
            'success' => $ok,
            'msg' => $json['msg'] ?? $json['message'] ?? null,
            'captcha' => (bool) ($json['captcha'] ?? false),
            // 不记录 cookies
        ]);

        return [
            'success' => $ok,
            'code' => (int) ($json['code'] ?? $response->status()),
            'msg' => (string) ($json['msg'] ?? $json['message'] ?? ($ok ? '登录成功' : '登录失败')),
            'cookies' => $json['data']['cookies'] ?? $json['cookies'] ?? [],
            'user_agent' => $json['data']['user_agent'] ?? $payload['user_agent'],
            'captcha' => (bool) ($json['captcha'] ?? $json['data']['captcha'] ?? false),
        ];
    }

    /**
     * 检测 Cookie 会话是否有效
     *
     * @return array{valid:bool,msg:string}
     */
    public function checkCookie(string $platform, string $proxy, array $cookies, string $userAgent): array
    {
        $base = rtrim((string) config('services.python_login.base_url'), '/');
        $path = (string) config('services.python_login.check_cookie_path', '/api/check-cookie');
        $timeout = (int) config('services.python_login.timeout', 120);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($base.$path, [
                    'platform' => $platform,
                    'proxy_server_addr' => $proxy,
                    'cookies' => $cookies,
                    'user_agent' => $userAgent,
                ]);
        } catch (\Throwable $e) {
            Log::warning('python_check_cookie.http_error', ['error' => $e->getMessage()]);

            return ['valid' => false, 'msg' => '会话检测服务不可用'];
        }

        $json = $response->json() ?? [];
        $valid = $response->successful() && (($json['code'] ?? 0) === 200) && (($json['data']['valid'] ?? false) === true);

        return [
            'valid' => $valid,
            'msg' => (string) ($json['msg'] ?? ($valid ? '会话有效' : '会话失效')),
        ];
    }
}
