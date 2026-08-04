<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * 也可手动执行：
 * php artisan cookie:refresh-all
 * php artisan cookie:refresh-all --force
 *
 * 调度器需常驻：php artisan schedule:work
 * 或 crontab: * * * * * php /path/to/artisan schedule:run
 */
