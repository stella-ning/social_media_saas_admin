<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 小红书评论引流闭环：
 * - crawler_tasks 扩展采集/主页核验/自动回复开关（对应需求 spider_task 字段）
 * - spider_comment_record 评论操作日志
 * - marketing_account_blacklist 营销号黑名单
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawler_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('crawler_tasks', 'enable_comment_collect')) {
                $table->unsignedTinyInteger('enable_comment_collect')->default(1)
                    ->after('daily_limit')->comment('开启评论采集');
            }
            if (!Schema::hasColumn('crawler_tasks', 'enable_user_homepage_check')) {
                $table->unsignedTinyInteger('enable_user_homepage_check')->default(0)
                    ->comment('开启用户主页核验');
            }
            if (!Schema::hasColumn('crawler_tasks', 'auto_comment_reply')) {
                $table->unsignedTinyInteger('auto_comment_reply')->default(0)
                    ->comment('AI自动评论回复');
            }
            if (!Schema::hasColumn('crawler_tasks', 'reply_interval')) {
                $table->unsignedInteger('reply_interval')->default(90)
                    ->comment('评论发送间隔秒数');
            }
            if (!Schema::hasColumn('crawler_tasks', 'daily_reply_max')) {
                $table->unsignedInteger('daily_reply_max')->default(30)
                    ->comment('单日最大回复条数');
            }
            if (!Schema::hasColumn('crawler_tasks', 'today_reply_count')) {
                $table->unsignedInteger('today_reply_count')->default(0)
                    ->comment('今日已回复条数');
            }
        });

        if (!Schema::hasTable('spider_comment_record')) {
            Schema::create('spider_comment_record', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index()->comment('爬虫任务ID');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('note_url', 512)->nullable()->comment('笔记链接');
                $table->string('comment_user_name', 128)->nullable();
                $table->string('comment_user_id', 64)->nullable()->index()->comment('平台用户ID');
                $table->text('comment_content')->nullable();
                $table->string('user_homepage_url', 512)->nullable();
                $table->string('homepage_check_result', 32)->nullable()
                    ->comment('pending/real_consumer/marketing/unknown/skipped');
                $table->json('homepage_meta')->nullable()->comment('主页笔记数/粉丝等');
                $table->boolean('is_inquiry')->default(false)->comment('第一层是否咨询意向');
                $table->string('funnel_stage', 32)->default('collected')
                    ->comment('collected/filtered/homepage_failed/blacklisted/ready_reply/replied/sensitive_block/failed');
                $table->text('ai_reply_content')->nullable();
                $table->string('sensitive_word_check_status', 32)->nullable()
                    ->comment('pass/warn/block/regenerated');
                $table->string('hit_sensitive_words', 255)->nullable();
                $table->unsignedBigInteger('operate_social_account')->nullable()->comment('执行社媒账号ID');
                $table->string('run_status', 32)->default('pending')
                    ->comment('pending/processing/success/failed/skipped');
                $table->unsignedBigInteger('message_session_id')->nullable();
                $table->unsignedBigInteger('crm_lead_id')->nullable();
                $table->string('fail_reason', 255)->nullable();
                $table->timestamp('create_time')->useCurrent();
                $table->timestamps();

                $table->index(['tenant_id', 'funnel_stage']);
                $table->index(['task_id', 'run_status']);
            });
        }

        if (!Schema::hasTable('marketing_account_blacklist')) {
            Schema::create('marketing_account_blacklist', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('platform', 32)->default('xiaohongshu');
                $table->string('platform_user_id', 64)->nullable();
                $table->string('nickname', 128)->nullable();
                $table->string('homepage_url', 512)->nullable();
                $table->string('reason', 255)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'platform', 'platform_user_id'], 'uniq_tenant_platform_uid');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_account_blacklist');
        Schema::dropIfExists('spider_comment_record');

        Schema::table('crawler_tasks', function (Blueprint $table) {
            foreach ([
                'enable_comment_collect', 'enable_user_homepage_check', 'auto_comment_reply',
                'reply_interval', 'daily_reply_max', 'today_reply_count',
            ] as $col) {
                if (Schema::hasColumn('crawler_tasks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
