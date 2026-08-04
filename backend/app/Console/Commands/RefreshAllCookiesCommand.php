<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\SocialAccountService;
use Illuminate\Console\Command;

/**
 * 定时刷新 Cookie：
 * php artisan cookie:refresh-all
 *
 * 流程：循环在线账号 → 检测会话 → 失效则用专属代理+固定指纹重新登录
 * 连续 3 次失败 → 置离线并写风险提示
 */
class RefreshAllCookiesCommand extends Command
{
    protected $signature = 'cookie:refresh-all {--force : 跳过会话检测，强制重新登录}';

    protected $description = '检测并刷新所有在线社媒账号 Cookie（每 6 小时调度）';

    public function handle(SocialAccountService $service): int
    {
        $force = (bool) $this->option('force');
        $accounts = SocialAccount::query()
            ->with(['proxy', 'activeCookie'])
            ->where('account_status', 1)
            ->orderBy('id')
            ->get();

        $this->info('待处理在线账号：'.$accounts->count());

        $ok = 0;
        $fail = 0;
        $skip = 0;

        foreach ($accounts as $account) {
            $this->line("处理账号 #{$account->id} ...");

            if (!$force) {
                $check = $service->checkLogin($account);
                if ($check['valid']) {
                    $skip++;
                    $this->info("  会话仍有效，跳过");
                    continue;
                }
            }

            if ($service->refreshCookie($account->fresh(['proxy']))) {
                $ok++;
                $this->info('  刷新成功');
            } else {
                $fail++;
                $this->warn('  刷新失败');
            }
        }

        $this->info("完成：成功 {$ok} / 失败 {$fail} / 跳过 {$skip}");

        return self::SUCCESS;
    }
}
