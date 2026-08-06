<?php

namespace Database\Seeders;

use App\Models\CrawlerTask;
use App\Models\SensitiveWord;
use App\Models\SpiderCommentRecord;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 评论引流演示数据
 */
class CommentFunnelDemoSeeder extends Seeder
{
    public function run(): void
    {
        SensitiveWord::query()->firstOrCreate(
            ['tenant_id' => null, 'word' => '日入过万'],
            ['level' => 'block']
        );
        SensitiveWord::query()->firstOrCreate(
            ['tenant_id' => null, 'word' => '稳赚'],
            ['level' => 'block']
        );

        $yx = Tenant::query()->orderBy('id')->first();
        if (!$yx) {
            return;
        }
        SensitiveWord::query()->firstOrCreate(
            ['tenant_id' => $yx->id, 'word' => '加微信'],
            ['level' => 'block']
        );

        $task = CrawlerTask::query()->where('tenant_id', $yx->id)->orderBy('id')->first();
        if ($task) {
            $task->update([
                'enable_comment_collect' => 1,
                'enable_user_homepage_check' => 1,
                'auto_comment_reply' => 1,
                'reply_interval' => 90,
                'daily_reply_max' => 30,
            ]);
        }

        if ($task && !SpiderCommentRecord::query()->where('task_id', $task->id)->exists()) {
            SpiderCommentRecord::create([
                'task_id' => $task->id,
                'tenant_id' => $yx->id,
                'note_url' => 'https://www.xiaohongshu.com/explore/demo_seed',
                'comment_user_name' => '种子用户小美',
                'comment_user_id' => 'seed_u_1',
                'comment_content' => '这个多少钱，想了解怎么买',
                'user_homepage_url' => 'https://www.xiaohongshu.com/user/profile/seed',
                'homepage_check_result' => 'real_consumer',
                'homepage_meta' => ['note_count' => 8, 'fans_count' => 55],
                'is_inquiry' => true,
                'funnel_stage' => 'ready_reply',
                'operate_social_account' => $task->social_account_id,
                'run_status' => 'success',
                'create_time' => now(),
            ]);
        }

        $this->command?->info('CommentFunnelDemoSeeder done');
    }
}
