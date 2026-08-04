<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'SocialAI SaaS API',
        'version' => 'v1.0.0',
        'docs' => '/api',
    ]);
});
