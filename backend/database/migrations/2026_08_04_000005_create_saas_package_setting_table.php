<?php

/**
 * 套餐权限配置主表 saas_package_setting
 * package_type: 1基础 / 2专业 / 3企业
 * 数值字段为 null 表示无上限
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_package_setting', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('package_type')->unique()->comment('1基础版 2专业版 3企业版');
            $table->unsignedTinyInteger('max_template_level')->default(1)->comment('允许选用的最高 AI 参数模板等级');
            $table->unsignedInteger('max_prompt')->nullable()->comment('Prompt 上限，null=无限');
            $table->unsignedInteger('max_knowledge')->nullable()->comment('知识库文档上限，null=无限');
            $table->unsignedInteger('daily_ai_limit')->nullable()->comment('每日AI调用配额，null=无限');
            $table->unsignedInteger('max_crawler_task')->nullable()->comment('爬虫任务上限，null=无限');
            $table->unsignedInteger('max_social_account')->nullable()->comment('社媒账号绑定上限，null=无限');
            $table->json('allow_platforms')->nullable()->comment('允许平台：xiaohongshu/douyin/channels');
            $table->unsignedTinyInteger('enable_account_ai_config')->default(0)->comment('是否允许小红书账号独立AI配置');
            $table->unsignedTinyInteger('enable_account_knowledge')->default(0)->comment('是否允许账号专属知识库');
            $table->unsignedTinyInteger('enable_custom_api_key')->default(0)->comment('是否允许自定义API-Key（企业）');
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('saas_package_setting')->insert([
            [
                'package_type' => 1,
                'max_template_level' => 1,
                'max_prompt' => 3,
                'max_knowledge' => 5,
                'daily_ai_limit' => 800,
                'max_crawler_task' => 5,
                'max_social_account' => 3,
                'allow_platforms' => json_encode(['xiaohongshu']),
                'enable_account_ai_config' => 0,
                'enable_account_knowledge' => 0,
                'enable_custom_api_key' => 0,
                'remark' => '基础版默认配置',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'package_type' => 2,
                'max_template_level' => 2,
                'max_prompt' => 15,
                'max_knowledge' => 30,
                'daily_ai_limit' => 8000,
                'max_crawler_task' => 30,
                'max_social_account' => 15,
                'allow_platforms' => json_encode(['xiaohongshu', 'douyin']),
                'enable_account_ai_config' => 1,
                'enable_account_knowledge' => 0,
                'enable_custom_api_key' => 0,
                'remark' => '专业版默认配置',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'package_type' => 3,
                'max_template_level' => 3,
                'max_prompt' => null,
                'max_knowledge' => null,
                'daily_ai_limit' => null,
                'max_crawler_task' => null,
                'max_social_account' => null,
                'allow_platforms' => json_encode(['xiaohongshu', 'douyin', 'channels']),
                'enable_account_ai_config' => 1,
                'enable_account_knowledge' => 1,
                'enable_custom_api_key' => 1,
                'remark' => '企业版默认配置',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_package_setting');
    }
};
