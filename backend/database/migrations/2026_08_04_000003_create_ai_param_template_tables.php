<?php

/**
 * AI 参数模板三层配置相关表
 * - saas_ai_param_template：租户多套 AI 模型参数模板（API-Key AES 加密）
 * - saas_ai_param_global：平台全局默认 AI 参数（优先级最低）
 * - saas_account_knowledge_rel：小红书账号专属知识库关联
 * - saas_social_account 增绑字段
 * - ai_prompt_templates 增加 is_default（租户默认话术）
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- 租户 AI 参数模板 ----------
        Schema::create('saas_ai_param_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete()->comment('所属租户，删除租户级联删除');
            $table->string('template_name', 128)->comment('模板名称');
            $table->string('ai_model', 128)->comment('模型名称，如 gpt-4o-mini');
            $table->text('encrypt_api_key')->comment('AES-256 加密后的 API-Key');
            $table->string('api_base_url', 255)->nullable()->comment('请求地址');
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedInteger('max_tokens')->default(2048);
            $table->decimal('top_p', 3, 2)->default(1.00);
            $table->unsignedInteger('daily_call_quota')->default(1000)->comment('每日调用配额');
            $table->unsignedTinyInteger('is_default')->default(0)->comment('1=租户默认模板');
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });

        // ---------- 平台全局默认 AI 参数 ----------
        Schema::create('saas_ai_param_global', function (Blueprint $table) {
            $table->id();
            $table->string('ai_model', 128)->default('gpt-4o-mini');
            $table->text('encrypt_api_key')->nullable();
            $table->string('api_base_url', 255)->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedInteger('max_tokens')->default(2048);
            $table->decimal('top_p', 3, 2)->default(1.00);
            $table->unsignedInteger('daily_call_quota')->default(1000);
            $table->timestamps();
        });

        // 写入一条平台默认配置（API-Key 可后续在系统设置中更新）
        DB::table('saas_ai_param_global')->insert([
            'ai_model' => 'gpt-4o-mini',
            'encrypt_api_key' => null,
            'api_base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.70,
            'max_tokens' => 2048,
            'top_p' => 1.00,
            'daily_call_quota' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ---------- 社媒账号绑定 AI 配置字段 ----------
        Schema::table('saas_social_account', function (Blueprint $table) {
            $table->unsignedBigInteger('bind_param_template_id')->nullable()->after('avatar')
                ->comment('绑定租户AI参数模板');
            $table->unsignedBigInteger('bind_prompt_id')->nullable()->after('bind_param_template_id')
                ->comment('绑定租户Prompt话术模板');
            $table->unsignedTinyInteger('enable_account_knowledge')->default(0)->after('bind_prompt_id')
                ->comment('1=启用账号专属知识库勾选');

            $table->index('bind_param_template_id');
            $table->index('bind_prompt_id');
        });

        // ---------- 账号-知识库中间表 ----------
        Schema::create('saas_account_knowledge_rel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('social_account_id')->comment('saas_social_account.id');
            $table->unsignedBigInteger('knowledge_id')->comment('knowledge_docs.id');
            $table->timestamps();

            $table->unique(['social_account_id', 'knowledge_id'], 'uniq_account_knowledge');
            $table->foreign('social_account_id', 'fk_akk_account')
                ->references('id')->on('saas_social_account')->cascadeOnDelete();
            $table->foreign('knowledge_id', 'fk_akk_knowledge')
                ->references('id')->on('knowledge_docs')->cascadeOnDelete();
        });

        // ---------- Prompt 模板增加租户默认标记 ----------
        Schema::table('ai_prompt_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_default')->default(0)->after('rules')
                ->comment('1=租户默认话术模板');
            $table->index(['tenant_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_prompt_templates', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_default']);
            $table->dropColumn('is_default');
        });

        Schema::dropIfExists('saas_account_knowledge_rel');

        Schema::table('saas_social_account', function (Blueprint $table) {
            $table->dropIndex(['bind_param_template_id']);
            $table->dropIndex(['bind_prompt_id']);
            $table->dropColumn(['bind_param_template_id', 'bind_prompt_id', 'enable_account_knowledge']);
        });

        Schema::dropIfExists('saas_ai_param_global');
        Schema::dropIfExists('saas_ai_param_template');
    }
};
