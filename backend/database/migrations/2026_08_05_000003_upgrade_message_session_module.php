<?php

/**
 * 消息会话模块迭代：
 * - message_sessions 扩展：账号关联、人工接管、意向标签、处理状态、闲置关闭、CRM 关联
 * - messages 扩展：已读状态、敏感词拦截标记
 * - saas_quick_replies：租户快捷回复话术库
 * - saas_sensitive_words：敏感词库
 * - saas_message_alert_logs：违禁告警日志
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('social_account_id')->nullable()->after('tenant_id')
                ->comment('产生会话的社媒账号');
            $table->unsignedTinyInteger('human_takeover')->default(0)->after('ai_auto_reply')
                ->comment('1=人工接管，暂停AI');
            $table->string('session_status', 16)->default('open')->after('human_takeover')
                ->comment('open/closed');
            $table->string('handle_status', 16)->default('unread')->after('session_status')
                ->comment('unread/read/processed');
            $table->string('intent_level', 16)->default('none')->after('handle_status')
                ->comment('none无意向/normal普通/high高意向');
            $table->string('consult_product', 128)->nullable()->after('intent_level')
                ->comment('咨询产品标签');
            $table->json('tags')->nullable()->after('consult_product')->comment('会话标签集合');
            $table->timestamp('last_visitor_at')->nullable()->after('tags')
                ->comment('访客最近发言时间，用于闲置超时');
            $table->timestamp('closed_at')->nullable()->after('last_visitor_at');
            $table->unsignedBigInteger('crm_lead_id')->nullable()->after('closed_at')
                ->comment('已推送的 CRM 线索');
            $table->index('social_account_id');
            $table->index(['tenant_id', 'session_status']);
            $table->index(['tenant_id', 'handle_status']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('read_status', 16)->default('unread')->after('content')
                ->comment('unread/read');
            $table->unsignedTinyInteger('is_blocked')->default(0)->after('read_status')
                ->comment('敏感词拦截未发送');
            $table->string('block_reason', 255)->nullable()->after('is_blocked');
        });

        Schema::create('saas_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->comment('租户隔离');
            $table->string('title', 64)->comment('话术标题');
            $table->text('content')->comment('话术正文');
            $table->string('category', 32)->nullable()->default('常用')->comment('分类');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index('tenant_id');
        });

        Schema::create('saas_sensitive_words', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('null=平台全局');
            $table->string('word', 64);
            $table->string('level', 16)->default('block')->comment('block拦截/warn告警');
            $table->timestamps();
            $table->unique(['tenant_id', 'word']);
        });

        Schema::create('saas_message_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('hit_words', 255)->comment('命中敏感词');
            $table->text('content_snippet')->nullable()->comment('截断摘要，不含全量敏感原文策略可裁剪');
            $table->string('action', 32)->default('blocked')->comment('blocked/warned');
            $table->timestamp('logged_at')->useCurrent();
            $table->index(['tenant_id', 'logged_at']);
        });

        // 平台默认敏感词
        $now = now();
        $globalWords = ['违禁药品', '枪支', '色情', '赌博', '刷单', '返现骗局', '虚假交易'];
        foreach ($globalWords as $w) {
            DB::table('saas_sensitive_words')->insert([
                'tenant_id' => null,
                'word' => $w,
                'level' => 'block',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 回填已有会话
        DB::table('message_sessions')->update([
            'session_status' => 'open',
            'handle_status' => DB::raw("CASE WHEN unread > 0 THEN 'unread' ELSE 'read' END"),
            'intent_level' => 'none',
            'last_visitor_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_message_alert_logs');
        Schema::dropIfExists('saas_sensitive_words');
        Schema::dropIfExists('saas_quick_replies');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['read_status', 'is_blocked', 'block_reason']);
        });

        Schema::table('message_sessions', function (Blueprint $table) {
            $table->dropIndex(['social_account_id']);
            $table->dropIndex(['tenant_id', 'session_status']);
            $table->dropIndex(['tenant_id', 'handle_status']);
            $table->dropColumn([
                'social_account_id', 'human_takeover', 'session_status', 'handle_status',
                'intent_level', 'consult_product', 'tags', 'last_visitor_at', 'closed_at', 'crm_lead_id',
            ]);
        });
    }
};
