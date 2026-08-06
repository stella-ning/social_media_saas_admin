<?php

namespace Database\Seeders;

use App\Models\IndustryPrompt;
use App\Models\PackageSetting;
use App\Models\ProxyIp;
use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Services\FinanceService;
use App\Services\PackageSettingService;
use Illuminate\Database\Seeder;

/**
 * 套餐重定价 + 平台公共代理托管 + 财务演示
 * php artisan db:seed --class=PackagePlatformProxySeeder
 */
class PackagePlatformProxySeeder extends Seeder
{
    public function run(): void
    {
        /** @var PackageSettingService $svc */
        $svc = app(PackageSettingService::class);
        foreach ([1, 2, 3] as $type) {
            $svc->reset($type);
        }

        // 平台公共池补充
        if (ProxyIp::query()->where('pool_type', 'public')->count() < 5) {
            for ($i = 1; $i <= 8; $i++) {
                ProxyIp::query()->firstOrCreate(
                    ['address' => "192.168.80.{$i}:8080"],
                    [
                        'location' => '平台住宅池-华东',
                        'protocol' => 'HTTP/HTTPS',
                        'status' => 'running',
                        'load' => random_int(0, 30),
                        'capacity' => 100,
                        'latency_ms' => random_int(20, 80),
                        'tenant_id' => null,
                        'pool_type' => 'public',
                        'risk_level' => 'low',
                        'platform_scope' => null,
                    ]
                );
            }
        }
        ProxyIp::query()->whereNull('pool_type')->orWhere('pool_type', '')->update(['pool_type' => 'public']);
        Tenant::query()->update([
            'allow_self_proxy' => 0,
            'package_version' => PackageSetting::PACKAGE_VERSION,
        ]);
        Tenant::query()->whereNull('package_expires_at')->update([
            'package_expires_at' => now()->addMonths(3),
        ]);

        $prompts = [
            ['title' => '美妆代理开场', 'industry' => '美妆', 'min' => 1, 'content' => '哈喽在的，我们是源头工厂，代理门槛不高，方便说下你在哪个城市吗？'],
            ['title' => '服饰拿货咨询', 'industry' => '服饰', 'min' => 2, 'content' => '亲，现货当天发，一件也能拿，想看哪一类我发链接给你～'],
            ['title' => '食品溯源话术', 'industry' => '食品', 'min' => 2, 'content' => '我们批次可溯源，门店批发和线上分销都有政策，方便留个联系方式吗？'],
            ['title' => '企业定制白皮书', 'industry' => '综合', 'min' => 3, 'content' => '可根据你们品类定制行业话术与知识库，一对一对齐口径后再上线接待。'],
        ];
        foreach ($prompts as $i => $p) {
            IndustryPrompt::query()->updateOrCreate(
                ['title' => $p['title']],
                [
                    'industry' => $p['industry'],
                    'content' => $p['content'],
                    'min_package_type' => $p['min'],
                    'template_level' => min(3, $p['min']),
                    'is_published' => 1,
                    'sort' => $i + 1,
                ]
            );
        }

        /** @var FinanceService $finance */
        $finance = app(FinanceService::class);
        foreach (Tenant::query()->where('status', 1)->get() as $tenant) {
            $setting = PackageSetting::findByPackageCode((string) $tenant->package);
            if (!$setting) {
                continue;
            }
            TenantOrder::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'package_code' => $tenant->package,
                    'package_version' => PackageSetting::PACKAGE_VERSION,
                ],
                [
                    'order_no' => 'PODEMO'.$tenant->id.now()->format('md'),
                    'price_monthly' => (int) $setting->price_monthly,
                    'months' => 3,
                    'amount' => (int) $setting->price_monthly * 3,
                    'status' => 'paid',
                    'starts_at' => now()->subMonth(),
                    'expires_at' => $tenant->package_expires_at ?: now()->addMonths(2),
                    'remark' => '演示订单 · 平台公共代理托管',
                ]
            );
        }
        $finance->aggregateDailyCosts(now()->format('Y-m-d'));
        $finance->aggregateDailyCosts(now()->subDay()->format('Y-m-d'));
        $finance->aggregateDailyCosts(now()->subDays(2)->format('Y-m-d'));

        $this->command?->info('套餐定价/公共代理托管/行业Prompt/财务台账演示数据已就绪');
    }
}
