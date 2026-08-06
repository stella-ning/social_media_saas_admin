<?php

namespace Database\Seeders;

use App\Models\AccountCookie;
use App\Models\AccountKnowledgeRel;
use App\Models\AccountOperationLog;
use App\Models\AiParamTemplate;
use App\Models\AiPromptTemplate;
use App\Models\CrawlerTask;
use App\Models\CrawlerTaskLog;
use App\Models\CrmLead;
use App\Models\KnowledgeDoc;
use App\Models\Message;
use App\Models\MessageSession;
use App\Models\PackageSetting;
use App\Models\ProxyIp;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\TenantProxy;
use App\Support\AesCrypto;
use App\Support\BrowserFingerprint;
use App\Support\PlatformEnum;
use Illuminate\Database\Seeder;

/**
 * 完整业务链路演示数据
 *
 * 链路：
 * 套餐权限 → 租户配额 → 代理IP池 → 社媒账号(一号一IP) → Cookie
 *        → AI参数/Prompt/知识库 → 账号级AI绑定 → 爬虫任务 → 日志 → CRM线索 → 私信会话
 *
 * 执行：
 *   php artisan db:seed --class=BusinessFlowDemoSeeder
 *   # 或在 migrate:fresh --seed 后由 DatabaseSeeder 调用
 */
class BusinessFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('=== 开始写入完整业务链路演示数据 ===');

        // 1) AI 配置中心（参数模板 / Prompt / 知识库）
        $this->call(AiConfigDemoSeeder::class);

        $yx = Tenant::query()->where('name', 'like', '%悦享%')->first()
            ?? Tenant::query()->where('package', 'ent')->orderBy('id')->first();
        $cf = Tenant::query()->where('name', 'like', '%晨风%')->first()
            ?? Tenant::query()->where('package', 'pro')->orderBy('id')->first();
        $jg = Tenant::query()->where('name', 'like', '%极光%')->first()
            ?? Tenant::query()->where('package', 'basic')->orderBy('id')->first();

        if (!$yx || !$cf || !$jg) {
            $this->command?->error('缺少基础租户，请先执行 DatabaseSeeder');

            return;
        }

        // 2) 按套餐同步租户配额（含代理 IP）
        $this->syncTenantQuotaFromPackage($yx);
        $this->syncTenantQuotaFromPackage($cf);
        $this->syncTenantQuotaFromPackage($jg);
        // 基础版演示也启用，便于完整链路联调
        $jg->update(['status' => 1]);

        // 3) 代理 IP + 租户分配
        $yxProxies = $this->ensureProxies($yx, [
            ['addr' => '123.56.78.102:8080', 'loc' => '广东深圳'],
            ['addr' => '118.31.10.20:8888', 'loc' => '浙江杭州'],
            ['addr' => '39.108.12.33:9000', 'loc' => '广东广州'],
            ['addr' => '120.77.55.66:8118', 'loc' => '广东东莞'], // 空闲备用
        ]);
        $cfProxies = $this->ensureProxies($cf, [
            ['addr' => '47.100.23.45:3128', 'loc' => '上海浦东'],
            ['addr' => '101.132.88.19:8080', 'loc' => '上海静安'],
            ['addr' => '106.14.22.77:3128', 'loc' => '上海虹桥'], // 空闲备用
        ]);
        $jgProxies = $this->ensureProxies($jg, [
            ['addr' => '14.215.177.38:8080', 'loc' => '广东深圳'],
            ['addr' => '113.96.10.55:8888', 'loc' => '广东深圳'], // 空闲备用
        ]);

        // 4) 社媒账号（状态正常 + 一号一IP）覆盖套餐允许平台
        $yxAccounts = [
            $this->ensureAccount($yx, PlatformEnum::XHS, '13800001111', '穿搭博主小美', $yxProxies[0], 1),
            $this->ensureAccount($yx, PlatformEnum::XHS, '13800001112', '悦享美妆官号', $yxProxies[1], 1),
            $this->ensureAccount($yx, PlatformEnum::DOUYIN, '13800002221', '悦享抖音旗舰', $yxProxies[2], 1),
            $this->ensureAccount($yx, PlatformEnum::CHANNELS, 'yx_channels_01', '悦享视频号', $yxProxies[3] ?? $yxProxies[0], 1),
        ];
        $cfAccounts = [
            $this->ensureAccount($cf, PlatformEnum::XHS, '13900003331', '晨风穿搭日记', $cfProxies[0], 1),
            $this->ensureAccount($cf, PlatformEnum::DOUYIN, '13900002222', '晨风服饰官方', $cfProxies[1], 1),
        ];
        // 离线演示账号（不可用于新建爬虫）
        $this->ensureAccount($cf, PlatformEnum::DOUYIN, '13900002999', '晨风离线号', $cfProxies[2] ?? $cfProxies[0], 0, '演示：离线账号');

        $jgAccounts = [
            $this->ensureAccount($jg, PlatformEnum::XHS, '13500004441', '极光好物笔记', $jgProxies[0], 1),
        ];

        // 5) Cookie（调度器必需）
        foreach (array_merge($yxAccounts, $cfAccounts, $jgAccounts) as $acc) {
            if ((int) $acc->account_status === 1) {
                $this->ensureCookie($acc);
                $this->ensureOpLog($acc, 'login', '演示数据：自动登录成功，Cookie 已落库');
            }
        }

        // 6) 账号级 AI（企业版小红书独立配置）
        $yxPrompt = AiPromptTemplate::query()->where('tenant_id', $yx->id)->where('is_default', 1)->first()
            ?? AiPromptTemplate::query()->where('tenant_id', $yx->id)->first();
        $yxParam = AiParamTemplate::query()->where('tenant_id', $yx->id)->where('is_default', 1)->first()
            ?? AiParamTemplate::query()->where('tenant_id', $yx->id)->orderByDesc('template_level')->first();
        $yxDocs = KnowledgeDoc::query()->where('tenant_id', $yx->id)->orderBy('id')->limit(2)->pluck('id');

        if ($yxParam && $yxAccounts[0]->isXiaohongshu()) {
            $yxAccounts[0]->update([
                'bind_param_template_id' => $yxParam->id,
                'bind_prompt_id' => $yxPrompt?->id,
                'enable_account_knowledge' => 1,
            ]);
            AccountKnowledgeRel::query()->where('social_account_id', $yxAccounts[0]->id)->delete();
            foreach ($yxDocs as $kid) {
                AccountKnowledgeRel::query()->firstOrCreate([
                    'social_account_id' => $yxAccounts[0]->id,
                    'knowledge_id' => $kid,
                ]);
            }
            $this->ensureOpLog($yxAccounts[0], 'ai_config', '演示数据：绑定账号独立 AI 参数/Prompt/知识库');
        }

        // 7) 爬虫任务（强制绑定 social_account_id + 专属代理链路）
        $tasks = [];
        $tasks[] = $this->ensureCrawlerTask([
            'name' => '全网美妆关键词监控',
            'platform' => '小红书',
            'task_type' => 'keyword',
            'keywords' => '护肤品, 祛痘, 面膜',
            'tenant_id' => $yx->id,
            'social_account_id' => $yxAccounts[0]->id,
            'frequency' => '每2小时',
            'status' => 'running',
            'today_count' => 428,
            'daily_limit' => 500,
        ], '任务创建并启动，执行账号='.$yxAccounts[0]->account_name.'，强制代理：'.$yxAccounts[0]->proxy?->address);

        $tasks[] = $this->ensureCrawlerTask([
            'name' => '悦享抖音竞品监控',
            'platform' => '抖音',
            'task_type' => 'monitor',
            'keywords' => '@竞品A, @竞品B',
            'tenant_id' => $yx->id,
            'social_account_id' => $yxAccounts[2]->id,
            'frequency' => '每6小时',
            'status' => 'running',
            'today_count' => 86,
            'daily_limit' => 800,
        ], '任务创建并启动，执行账号='.$yxAccounts[2]->account_name.'，强制代理：'.$yxAccounts[2]->proxy?->address);

        $tasks[] = $this->ensureCrawlerTask([
            'name' => '晨风穿搭关键词采集',
            'platform' => '小红书',
            'task_type' => 'keyword',
            'keywords' => '夏季连衣裙, 通勤穿搭',
            'tenant_id' => $cf->id,
            'social_account_id' => $cfAccounts[0]->id,
            'frequency' => '每2小时',
            'status' => 'running',
            'today_count' => 156,
            'daily_limit' => 500,
        ], '任务创建并启动，执行账号='.$cfAccounts[0]->account_name.'，强制代理：'.$cfAccounts[0]->proxy?->address);

        $tasks[] = $this->ensureCrawlerTask([
            'name' => '同行评论区引流',
            'platform' => '抖音',
            'task_type' => 'monitor',
            'keywords' => '@晨风服饰, @极光贸易',
            'tenant_id' => $cf->id,
            'social_account_id' => $cfAccounts[1]->id,
            'frequency' => '每4小时',
            'status' => 'paused',
            'today_count' => 12,
            'daily_limit' => 500,
        ], '任务已暂停（演示）');

        $tasks[] = $this->ensureCrawlerTask([
            'name' => '极光基础版商品采集',
            'platform' => '小红书',
            'task_type' => 'keyword',
            'keywords' => '源头工厂, 一件代发',
            'tenant_id' => $jg->id,
            'social_account_id' => $jgAccounts[0]->id,
            'frequency' => '每天一次',
            'status' => 'running',
            'today_count' => 32,
            'daily_limit' => 200,
        ], '任务创建并启动，执行账号='.$jgAccounts[0]->account_name.'，强制代理：'.$jgAccounts[0]->proxy?->address);

        // 补采集日志
        foreach ($tasks as $i => $task) {
            if ($task->status === 'running') {
                CrawlerTaskLog::query()->firstOrCreate(
                    [
                        'task_id' => $task->id,
                        'content' => '本轮采集完成，新增线索 '.(20 + $i * 7).' 条',
                    ],
                    [
                        'type' => 'success',
                        'logged_at' => now()->subHours(2 + $i),
                    ]
                );
            }
        }

        // 8) CRM 线索（承接爬虫产出）
        $this->ensureLead([
            'nickname' => '爱生活的Lily',
            'phone' => '138****9901',
            'quote' => '这款护肤品怎么代理？',
            'channel' => '小红书',
            'tenant_id' => $yx->id,
            'score' => 95,
            'intent' => 'high',
            'status' => '已接洽',
            'follower' => '张三(业务员A)',
            'tags' => ['代理意向', '美妆'],
        ]);
        $this->ensureLead([
            'nickname' => '护肤小白酱',
            'phone' => '136****1122',
            'quote' => '祛痘面膜有没有批发价',
            'channel' => '小红书',
            'tenant_id' => $yx->id,
            'score' => 88,
            'intent' => 'high',
            'status' => '未处理',
            'follower' => '张三(业务员A)',
            'tags' => ['询价'],
        ]);
        $this->ensureLead([
            'nickname' => '科技发烧友',
            'phone' => '155****8822',
            'quote' => '求批发价，私我',
            'channel' => '抖音',
            'tenant_id' => $cf->id,
            'score' => 82,
            'intent' => 'mid',
            'status' => '未处理',
            'follower' => '李四(业务员B)',
            'tags' => ['询价'],
        ]);
        $this->ensureLead([
            'nickname' => '极光探店君',
            'phone' => '137****5566',
            'quote' => '一件代发怎么合作',
            'channel' => '小红书',
            'tenant_id' => $jg->id,
            'score' => 76,
            'intent' => 'mid',
            'status' => '未处理',
            'follower' => '王五',
            'tags' => ['代发'],
        ]);

        // 9) 私信会话（AI 自动回复链路）
        $session = MessageSession::query()->updateOrCreate(
            [
                'tenant_id' => $yx->id,
                'name' => '爱生活的Lily',
                'platform' => '小红书',
            ],
            [
                'avatar' => 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
                'last_msg' => '你好，请问这款护肤品怎么代理？',
                'time_label' => '10:45',
                'unread' => 1,
                'date_label' => now()->format('Y-m-d').' 10:30',
                'ai_auto_reply' => true,
            ]
        );
        if ($session->messages()->count() === 0) {
            Message::create([
                'session_id' => $session->id,
                'from_type' => 'user',
                'content' => '你好，请问这款护肤品怎么代理？需要什么条件吗？',
            ]);
            Message::create([
                'session_id' => $session->id,
                'from_type' => 'ai',
                'content' => '您好！我是您的智能助理。我们品牌目前的代理政策非常友好，包含技术指导和货源支持。为了给您提供更精确的信息，能先了解下您的意向城市吗？',
            ]);
        }

        $this->printSummary($yx, $cf, $jg);
        $this->command?->info('=== 完整业务链路演示数据写入完成 ===');
        $this->command?->info('演示账号：admin / yx_admin / zhangsan ，密码均为 password123');
    }

    private function syncTenantQuotaFromPackage(Tenant $tenant): void
    {
        $setting = PackageSetting::findByPackageCode((string) $tenant->package);
        $defaults = match ($tenant->package) {
            'ent' => ['concurrent' => 50, 'ai_quota' => 50000, 'binds' => 50, 'kb' => 50],
            'pro' => ['concurrent' => 20, 'ai_quota' => 10000, 'binds' => 20, 'kb' => 10],
            default => ['concurrent' => 5, 'ai_quota' => 1000, 'binds' => 5, 'kb' => 1],
        };

        $payload = array_merge($defaults, [
            'max_proxy_ip' => $setting?->max_proxy_ip,
            'daily_proxy_request_limit' => $setting?->daily_proxy_request_limit,
            'allow_self_proxy' => $setting ? (int) $setting->allow_self_proxy : ($tenant->package === 'basic' ? 0 : 1),
            'status' => 1,
        ]);
        $tenant->update($payload);
    }

    /** @return ProxyIp[] */
    private function ensureProxies(Tenant $tenant, array $defs): array
    {
        $list = [];
        foreach ($defs as $i => $def) {
            $ip = ProxyIp::query()->updateOrCreate(
                ['address' => $def['addr']],
                [
                    'location' => $def['loc'],
                    'protocol' => 'HTTP/HTTPS',
                    'status' => 'idle',
                    'load' => 0,
                    'capacity' => 100,
                    'latency_ms' => 20 + $i * 5,
                    'tenant_id' => $tenant->id,
                ]
            );
            TenantProxy::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'proxy_ip_id' => $ip->id,
            ]);
            $list[] = $ip;
        }

        return $list;
    }

    private function ensureAccount(
        Tenant $tenant,
        int $platform,
        string $accountName,
        string $displayName,
        ProxyIp $proxy,
        int $status,
        ?string $riskTip = null
    ): SocialAccount {
        // 一号一IP：若代理已被其他账号占用，保持原绑定或换绑本账号
        $occupied = SocialAccount::query()
            ->where('bind_proxy_id', $proxy->id)
            ->where('account_name', '!=', $accountName)
            ->exists();

        $fp = BrowserFingerprint::generate($tenant->id.'|'.$platform.'|'.$accountName);
        $account = SocialAccount::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'account_name' => $accountName,
            ],
            [
                'platform' => $platform,
                'encrypt_pwd' => AesCrypto::encrypt('demo_password'),
                'bind_proxy_id' => $occupied ? null : $proxy->id,
                'browser_user_agent' => $fp['user_agent'],
                'browser_viewport' => $fp['viewport'],
                'account_status' => $status,
                'display_name' => $displayName,
                'avatar' => 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
                'last_refresh_cookie' => $status === 1 ? now() : null,
                'risk_tip' => $riskTip,
                'login_fail_count' => 0,
            ]
        );

        // 若新建时代理被占，再找空闲代理
        if (!$account->bind_proxy_id) {
            $used = SocialAccount::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('bind_proxy_id')
                ->pluck('bind_proxy_id');
            $free = ProxyIp::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotIn('id', $used)
                ->first();
            if ($free) {
                $account->update(['bind_proxy_id' => $free->id]);
                $proxy = $free;
            } else {
                // 最后手段：新建一条代理
                $proxy = ProxyIp::create([
                    'address' => '10.0.'.$tenant->id.'.'.random_int(10, 200).':8080',
                    'location' => '演示机房',
                    'protocol' => 'HTTP/HTTPS',
                    'status' => 'idle',
                    'load' => 0,
                    'capacity' => 100,
                    'tenant_id' => $tenant->id,
                ]);
                TenantProxy::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'proxy_ip_id' => $proxy->id,
                ]);
                $account->update(['bind_proxy_id' => $proxy->id]);
            }
        }

        $account->load('proxy');
        if ($account->proxy) {
            $account->proxy->update([
                'status' => $status === 1 ? 'running' : 'idle',
                'load' => $status === 1 ? max(1, (int) $account->proxy->load) : 0,
                'tenant_id' => $tenant->id,
            ]);
        }

        return $account->fresh('proxy');
    }

    private function ensureCookie(SocialAccount $account): void
    {
        $platform = PlatformEnum::toPythonKey((int) $account->platform);
        $domain = match ((int) $account->platform) {
            PlatformEnum::DOUYIN => '.douyin.com',
            PlatformEnum::CHANNELS => '.weixin.qq.com',
            default => '.xiaohongshu.com',
        };

        // 失效旧 cookie，写入一条有效演示 Cookie
        AccountCookie::query()
            ->where('social_account_id', $account->id)
            ->update(['expire_status' => 0]);

        AccountCookie::query()->create([
            'social_account_id' => $account->id,
            'cookie_json' => json_encode([
                [
                    'name' => 'web_session',
                    'value' => 'demo_'.$platform.'_'.$account->id.'_'.substr(md5((string) $account->id), 0, 16),
                    'domain' => $domain,
                    'path' => '/',
                ],
                [
                    'name' => 'a1',
                    'value' => 'demo_a1_'.$account->id,
                    'domain' => $domain,
                    'path' => '/',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'expire_status' => 1,
            'created_at' => now(),
        ]);
    }

    private function ensureOpLog(SocialAccount $account, string $type, string $content): void
    {
        $exists = AccountOperationLog::query()
            ->where('social_account_id', $account->id)
            ->where('type', $type)
            ->where('content', $content)
            ->exists();
        if ($exists) {
            return;
        }
        AccountOperationLog::create([
            'social_account_id' => $account->id,
            'type' => $type,
            'content' => $content,
            'logged_at' => now(),
        ]);
    }

    private function ensureCrawlerTask(array $data, string $bootLog): CrawlerTask
    {
        $data['target'] = ($data['task_type'] === 'keyword' ? '关键词：' : '监控：').$data['keywords'];
        $task = CrawlerTask::query()->updateOrCreate(
            [
                'tenant_id' => $data['tenant_id'],
                'name' => $data['name'],
            ],
            $data
        );

        CrawlerTaskLog::query()->firstOrCreate(
            [
                'task_id' => $task->id,
                'content' => $bootLog,
            ],
            [
                'type' => $task->status === 'running' ? 'success' : 'warning',
                'logged_at' => now()->subHours(3),
            ]
        );

        return $task;
    }

    private function ensureLead(array $data): void
    {
        CrmLead::query()->updateOrCreate(
            [
                'tenant_id' => $data['tenant_id'],
                'nickname' => $data['nickname'],
                'channel' => $data['channel'],
            ],
            $data
        );
    }

    private function printSummary(Tenant $yx, Tenant $cf, Tenant $jg): void
    {
        $rows = [
            ['指标', '数值'],
            ['租户', Tenant::count()],
            ['代理IP', ProxyIp::count()],
            ['社媒账号', SocialAccount::count()],
            ['有效Cookie', AccountCookie::query()->where('expire_status', 1)->count()],
            ['AI参数模板', AiParamTemplate::count()],
            ['Prompt模板', AiPromptTemplate::count()],
            ['知识库文档', KnowledgeDoc::count()],
            ['爬虫任务', CrawlerTask::count()],
            ['运行中任务', CrawlerTask::query()->where('status', 'running')->count()],
            ['CRM线索', CrmLead::count()],
            ['私信会话', MessageSession::count()],
        ];
        $this->command?->table($rows[0], array_slice($rows, 1));

        $this->command?->line('链路示例（企业版悦享）：');
        $acc = SocialAccount::query()->where('tenant_id', $yx->id)->where('account_status', 1)->with('proxy')->first();
        $task = CrawlerTask::query()->where('tenant_id', $yx->id)->whereNotNull('social_account_id')->first();
        $this->command?->line(sprintf(
            '  套餐=%s → 账号=%s → 代理=%s → Cookie=%s → 爬虫=%s(social_account_id=%s)',
            $yx->package,
            $acc?->display_name,
            $acc?->proxy?->address,
            AccountCookie::query()->where('social_account_id', $acc?->id)->where('expire_status', 1)->exists() ? '有' : '无',
            $task?->name,
            $task?->social_account_id
        ));
    }
}
