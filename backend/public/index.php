<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 注意：需先 composer install 生成 vendor 后才能启动
if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'code' => 500,
        'msg' => '请先执行 composer install，或按 README 使用 Laravel 官方骨架挂载本业务代码',
        'data' => (object) [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
