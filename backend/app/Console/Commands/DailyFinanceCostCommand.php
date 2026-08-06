<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\FinanceService;
use App\Support\PackageQuota;
use Illuminate\Console\Command;

/**
 * 每日凌晨：汇总平台公共资源消耗与五项硬性成本；暂停到期套餐特权
 * php artisan finance:daily-cost
 */
class DailyFinanceCostCommand extends Command
{
    protected $signature = 'finance:daily-cost {--date= : 统计日期 Y-m-d，默认昨天}';

    protected $description = '统计当日平台公共资源消耗与全套成本台账';

    public function handle(FinanceService $finance): int
    {
        $date = $this->option('date') ?: now()->subDay()->format('Y-m-d');
        $n = $finance->aggregateDailyCosts($date);
        $this->info("已写入 {$n} 条租户资源消耗台账（{$date}）");

        $expired = 0;
        Tenant::query()->whereNotNull('package_expires_at')->each(function (Tenant $t) use (&$expired) {
            if (PackageQuota::isPackageExpired($t)) {
                PackageQuota::pausePrivilegesOnExpiry($t);
                $expired++;
            }
        });
        $this->info("到期套餐特权暂停：{$expired} 个租户");

        return self::SUCCESS;
    }
}
