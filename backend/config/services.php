<?php

/**
 * 第三方 / 内部微服务配置
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Python Playwright 自动登录服务
    |--------------------------------------------------------------------------
    */
    'python_login' => [
        'base_url' => env('PYTHON_LOGIN_SERVICE_URL', 'http://127.0.0.1:8100'),
        'timeout' => (int) env('PYTHON_LOGIN_TIMEOUT', 120),
        'auto_login_path' => '/api/auto-login',
        'check_cookie_path' => '/api/check-cookie',
        'comment_dry_run' => (bool) env('PYTHON_COMMENT_DRY_RUN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | AES-256 凭证加密（禁止用 APP_KEY 直接当业务密钥时可单独配置）
    |--------------------------------------------------------------------------
    */
    'aes' => [
        // 32 字节密钥，可用: php -r "echo bin2hex(random_bytes(16));"
        'key' => env('AES_SECRET_KEY', env('APP_KEY')),
    ],
];
