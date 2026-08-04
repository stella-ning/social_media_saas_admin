<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * auth 配置片段：默认 guard + Sanctum
 * 合并进官方 config/auth.php 的 providers.users.model
 */
return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],
];
