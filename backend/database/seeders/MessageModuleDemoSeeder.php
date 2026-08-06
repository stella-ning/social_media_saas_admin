<?php

namespace Database\Seeders;

use App\Models\MessageSession;
use App\Models\QuickReply;
use App\Models\SensitiveWord;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Services\MessageService;
use Illuminate\Database\Seeder;

/**
 * 消息会话模块演示数据：快捷话术、敏感词、会话绑定账号、模拟访客接待链路
 * php artisan db:seed --class=MessageModuleDemoSeeder
 */
class MessageModuleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $yx = Tenant::query()->where('package', 'ent')->orderBy('id')->first();
        $cf = Tenant::query()->where('package', 'pro')->orderBy('id')->first();
        if (!$yx) {
            $this->command?->warn('无企业版租户，跳过');

            return;
        }

        foreach ([$yx, $cf] as $tenant) {
            if (!$tenant) {
                continue;
            }
            $replies = [
                ['title' => '问候开场', 'content' => '哈喽～在的，看到消息了，你想了解哪一块直接说就行。', 'sort' => 1],
                ['title' => '代理政策', 'content' => '我们这边源头拿货，代理门槛不高，方便说下你在哪个城市吗？', 'sort' => 2],
                ['title' => '引导留资', 'content' => '好的，我帮你问问名额。你方便留个联系方式，我把资料发你～', 'sort' => 3],
            ];
            foreach ($replies as $r) {
                QuickReply::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'title' => $r['title']],
                    ['content' => $r['content'], 'category' => '常用', 'sort' => $r['sort']]
                );
            }
            SensitiveWord::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'word' => '绝对稳赚'],
                ['level' => 'block']
            );
        }

        $acc = SocialAccount::query()
            ->where('tenant_id', $yx->id)
            ->where('account_status', 1)
            ->where('platform', 1)
            ->orderBy('id')
            ->first();

        $session = MessageSession::query()->updateOrCreate(
            [
                'tenant_id' => $yx->id,
                'name' => '爱生活的Lily',
                'platform' => '小红书',
            ],
            [
                'avatar' => 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png',
                'social_account_id' => $acc?->id,
                'ai_auto_reply' => true,
                'human_takeover' => 0,
                'session_status' => 'open',
                'handle_status' => 'unread',
                'intent_level' => 'none',
                'date_label' => now()->format('Y-m-d H:i'),
                'last_visitor_at' => now(),
            ]
        );

        /** @var MessageService $svc */
        $svc = app(MessageService::class);
        $svc->ingestVisitorMessage([
            'tenant_id' => $yx->id,
            'session_id' => $session->id,
            'name' => '爱生活的Lily',
            'platform' => '小红书',
            'social_account_id' => $acc?->id,
            'content' => '你好，这款护肤品怎么代理？需要什么条件吗？',
            'avatar' => $session->avatar,
        ]);

        $this->command?->info('消息模块演示数据已写入（快捷话术 / 敏感词 / 访客接待链路）');
    }
}
