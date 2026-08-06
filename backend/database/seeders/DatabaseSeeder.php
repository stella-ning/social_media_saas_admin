<?php

namespace Database\Seeders;

use App\Models\AiPromptTemplate;
use App\Models\CrawlerTask;
use App\Models\CrawlerTaskLog;
use App\Models\CrmLead;
use App\Models\KnowledgeDoc;
use App\Models\Message;
use App\Models\MessageSession;
use App\Models\ProxyIp;
use App\Models\SocialAccount;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 基础演示数据（对齐前端 mock）
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $yx = Tenant::create([
            'name' => '广州悦享科技有限公司',
            'contact' => '张晓明',
            'phone' => '13800138000',
            'email' => 'zhang@yxtech.com',
            'package' => 'ent',
            'status' => 1,
            'concurrent' => 45,
            'ai_quota' => 50000,
            'binds' => 30,
            'kb' => 50,
        ]);

        $cf = Tenant::create([
            'name' => '上海晨风服饰有限公司',
            'contact' => '李丽娜',
            'phone' => '13912345678',
            'email' => 'li@chenfeng.cn',
            'package' => 'pro',
            'status' => 1,
            'concurrent' => 12,
            'ai_quota' => 10000,
            'binds' => 10,
            'kb' => 10,
        ]);

        Tenant::create([
            'name' => '深圳极光贸易馆',
            'contact' => '王建国',
            'phone' => '13566778899',
            'email' => 'wang@jiguang.net',
            'package' => 'basic',
            'status' => 0,
            'concurrent' => 5,
            'ai_quota' => 1000,
            'binds' => 3,
            'kb' => 1,
        ]);

        // 演示账号，密码均为 password123
        User::create([
            'username' => 'admin',
            'display_name' => 'Admin',
            'password' => 'password123',
            'role' => 'super_admin',
            'tenant_id' => null,
            'status' => 1,
            'last_login_at' => now(),
        ]);
        User::create([
            'username' => 'yx_admin',
            'display_name' => '悦享管理员',
            'password' => 'password123',
            'role' => 'tenant_admin',
            'tenant_id' => $yx->id,
            'status' => 1,
        ]);
        User::create([
            'username' => 'zhangsan',
            'display_name' => '张三',
            'password' => 'password123',
            'role' => 'operator',
            'tenant_id' => $yx->id,
            'status' => 1,
        ]);

        // 代理 IP 先创建并分配给租户（租户隔离）
        $p1 = ProxyIp::create([
            'address' => '123.56.78.102:8080',
            'location' => '广东深圳',
            'protocol' => 'HTTP/HTTPS',
            'status' => 'idle',
            'load' => 0,
            'capacity' => 100,
            'latency_ms' => 35,
            'tenant_id' => $yx->id,
        ]);
        $p2 = ProxyIp::create([
            'address' => '47.100.23.45:3128',
            'location' => '上海浦东',
            'protocol' => 'HTTP/HTTPS',
            'status' => 'idle',
            'load' => 0,
            'capacity' => 100,
            'tenant_id' => $cf->id,
        ]);
        $p3 = ProxyIp::create([
            'address' => '118.31.10.20:8888',
            'location' => '浙江杭州',
            'protocol' => 'HTTP/HTTPS',
            'status' => 'idle',
            'load' => 0,
            'capacity' => 100,
            'tenant_id' => $yx->id,
        ]);

        \App\Models\TenantProxy::insert([
            ['tenant_id' => $yx->id, 'proxy_ip_id' => $p1->id, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $cf->id, 'proxy_ip_id' => $p2->id, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $yx->id, 'proxy_ip_id' => $p3->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 演示社媒账号（密码 AES 加密；真实 Cookie 需走自动登录）
        $acc1 = SocialAccount::create([
            'tenant_id' => $yx->id,
            'platform' => 1,
            'account_name' => '13800001111',
            'encrypt_pwd' => \App\Support\AesCrypto::encrypt('demo_password'),
            'bind_proxy_id' => $p1->id,
            'browser_user_agent' => \App\Support\BrowserFingerprint::generate('yx|1|13800001111')['user_agent'],
            'browser_viewport' => '1920x1080',
            'account_status' => 1,
            'display_name' => '穿搭博主小美',
            'avatar' => 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
            'last_refresh_cookie' => now(),
        ]);
        $p1->update(['status' => 'running', 'load' => 1]);

        SocialAccount::create([
            'tenant_id' => $cf->id,
            'platform' => 2,
            'account_name' => '13900002222',
            'encrypt_pwd' => \App\Support\AesCrypto::encrypt('demo_password'),
            'bind_proxy_id' => $p2->id,
            'browser_user_agent' => \App\Support\BrowserFingerprint::generate('cf|2|13900002222')['user_agent'],
            'browser_viewport' => '1920x1080',
            'account_status' => 0,
            'display_name' => '晨风服饰官方',
            'avatar' => 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
            'risk_tip' => '演示：离线账号',
        ]);
        $p2->update(['status' => 'running', 'load' => 1]);

        $task = CrawlerTask::create([
            'name' => '全网美妆关键词监控',
            'platform' => '小红书',
            'task_type' => 'keyword',
            'keywords' => '护肤品, 祛痘',
            'target' => '关键词：护肤品, 祛痘',
            'tenant_id' => $yx->id,
            'social_account_id' => $acc1->id,
            'frequency' => '每2小时',
            'status' => 'running',
            'today_count' => 428,
            'daily_limit' => 500,
        ]);
        CrawlerTask::create([
            'name' => '同行评论区引流',
            'platform' => '抖音',
            'task_type' => 'monitor',
            'keywords' => '@晨风服饰, @极光贸易',
            'target' => '监控：@晨风服饰, @极光贸易',
            'tenant_id' => $cf->id,
            'frequency' => '每4小时',
            'status' => 'paused',
            'today_count' => 12,
            'daily_limit' => 500,
        ]);
        CrawlerTaskLog::create([
            'task_id' => $task->id,
            'type' => 'success',
            'content' => '本轮采集完成，新增线索 48 条',
            'logged_at' => now()->subHours(2),
        ]);

        AiPromptTemplate::create([
            'tenant_id' => $yx->id,
            'category' => '社媒评论生成',
            'tag_type' => '',
            'name' => '国内评论生成默认模板',
            'desc' => '角色：源头工厂、实体商家、行业从业者，在小红书、抖音、视频号评论区友好互动...',
            'role' => '角色：源头工厂、实体商家、行业从业者，在小红书、抖音、视频号评论区友好互动。',
            'rules' => "1.贴合帖子内容自然回复；\n2.口语化、简短1-2句；",
        ]);

        KnowledgeDoc::create([
            'tenant_id' => $yx->id,
            'name' => '2026夏季服装货源手册.pdf',
            'size' => '2.4 MB',
            'status' => 'ready',
            'icon_color' => '#f56c6c',
        ]);

        CrmLead::create([
            'nickname' => '爱生活的Lily',
            'phone' => '138****9901',
            'quote' => '这款护肤品怎么代理？',
            'channel' => '小红书',
            'tenant_id' => $yx->id,
            'score' => 95,
            'intent' => 'high',
            'status' => '已接洽',
            'follower' => '张三(业务员A)',
            'tags' => ['代理意向'],
        ]);
        CrmLead::create([
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

        $session = MessageSession::create([
            'name' => '爱生活的Lily',
            'avatar' => 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
            'platform' => '小红书',
            'tenant_id' => $yx->id,
            'last_msg' => '你好，请问这款护肤品怎么代理？',
            'time_label' => '10:45',
            'unread' => 1,
            'date_label' => '2026-07-17 10:30',
            'ai_auto_reply' => true,
        ]);
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

        SystemSetting::create([
            'group' => 'basic',
            'key' => 'system_name',
            'value' => ['v' => '社媒AI自动化SaaS平台'],
        ]);

        // 补齐完整业务链路（Cookie / 账号AI / 爬虫绑定 / 线索 / 配额）
        $this->call(BusinessFlowDemoSeeder::class);
        $this->call(MessageModuleDemoSeeder::class);
        $this->call(PackagePlatformProxySeeder::class);
    }
}
