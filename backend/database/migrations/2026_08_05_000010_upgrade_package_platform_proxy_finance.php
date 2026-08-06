<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 套餐重定价 + 平台公共代理托管 + 财务/子账号/行业Prompt
 * - 全档套餐禁止租户自有代理（allow_self_proxy=0）
 * - 爬虫统一使用平台公共住宅代理池
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_package_setting', function (Blueprint $table) {
            if (!Schema::hasColumn('saas_package_setting', 'price_monthly')) {
                $table->unsignedInteger('price_monthly')->default(0)->after('package_type')
                    ->comment('月费（元）');
            }
            if (!Schema::hasColumn('saas_package_setting', 'premium_features')) {
                $table->json('premium_features')->nullable()->after('enable_custom_api_key')
                    ->comment('增值溢价功能清单 JSON');
            }
            if (!Schema::hasColumn('saas_package_setting', 'enable_sub_account')) {
                $table->unsignedTinyInteger('enable_sub_account')->default(0)->after('premium_features');
            }
            if (!Schema::hasColumn('saas_package_setting', 'enable_human_behavior')) {
                $table->unsignedTinyInteger('enable_human_behavior')->default(0)->after('enable_sub_account');
            }
            if (!Schema::hasColumn('saas_package_setting', 'enable_crm_auto_remind')) {
                $table->unsignedTinyInteger('enable_crm_auto_remind')->default(0)->after('enable_human_behavior');
            }
            if (!Schema::hasColumn('saas_package_setting', 'enable_excel_export')) {
                $table->unsignedTinyInteger('enable_excel_export')->default(0)->after('enable_crm_auto_remind');
            }
            if (!Schema::hasColumn('saas_package_setting', 'enable_dedicated_ip_pool')) {
                $table->unsignedTinyInteger('enable_dedicated_ip_pool')->default(0)->after('enable_excel_export');
            }
            if (!Schema::hasColumn('saas_package_setting', 'enable_ip_risk_check')) {
                $table->unsignedTinyInteger('enable_ip_risk_check')->default(0)->after('enable_dedicated_ip_pool');
            }
            if (!Schema::hasColumn('saas_package_setting', 'enable_ip_rotate')) {
                $table->unsignedTinyInteger('enable_ip_rotate')->default(0)->after('enable_ip_risk_check');
            }
            if (!Schema::hasColumn('saas_package_setting', 'enable_white_label')) {
                $table->unsignedTinyInteger('enable_white_label')->default(0)->after('enable_ip_rotate');
            }
        });

        // 强制全档关闭自有代理
        DB::table('saas_package_setting')->update(['allow_self_proxy' => 0]);

        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'package_expires_at')) {
                $table->timestamp('package_expires_at')->nullable()->after('package');
            }
            if (!Schema::hasColumn('tenants', 'package_version')) {
                $table->string('package_version', 32)->nullable()->after('package_expires_at')
                    ->comment('当前套餐版本号，如 2026.08');
            }
            if (!Schema::hasColumn('tenants', 'dedicated_ip_pool_enabled')) {
                $table->unsignedTinyInteger('dedicated_ip_pool_enabled')->default(0)
                    ->after('allow_self_proxy')->comment('企业版专属隔离公共IP池');
            }
            if (!Schema::hasColumn('tenants', 'ip_rotate_enabled')) {
                $table->unsignedTinyInteger('ip_rotate_enabled')->default(0)
                    ->after('dedicated_ip_pool_enabled');
            }
            if (!Schema::hasColumn('tenants', 'white_label_title')) {
                $table->string('white_label_title', 64)->nullable()->after('ip_rotate_enabled');
            }
        });
        DB::table('tenants')->update(['allow_self_proxy' => 0]);

        Schema::table('proxy_ips', function (Blueprint $table) {
            if (!Schema::hasColumn('proxy_ips', 'pool_type')) {
                $table->string('pool_type', 16)->default('public')->after('tenant_id')
                    ->comment('public平台公共池 / dedicated租户专属隔离池');
            }
            if (!Schema::hasColumn('proxy_ips', 'risk_level')) {
                $table->string('risk_level', 16)->default('low')->after('pool_type')
                    ->comment('low/medium/high');
            }
            if (!Schema::hasColumn('proxy_ips', 'last_risk_at')) {
                $table->timestamp('last_risk_at')->nullable()->after('risk_level');
            }
            if (!Schema::hasColumn('proxy_ips', 'rotate_group')) {
                $table->string('rotate_group', 32)->nullable()->after('last_risk_at');
            }
            if (!Schema::hasColumn('proxy_ips', 'platform_scope')) {
                $table->json('platform_scope')->nullable()->after('rotate_group')
                    ->comment('适用平台：xiaohongshu/douyin/channels，null=全平台');
            }
        });
        // 既有 IP 一律视为平台公共池，清空租户自有归属标记（保留分配关系 saas_tenant_proxy）
        DB::table('proxy_ips')->update(['pool_type' => 'public']);

        if (!Schema::hasTable('tenant_order')) {
            Schema::create('tenant_order', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('order_no', 64)->unique();
                $table->string('package_code', 16)->comment('basic/pro/ent');
                $table->string('package_version', 32)->nullable()->comment('套餐版本');
                $table->unsignedInteger('price_monthly')->default(0);
                $table->unsignedInteger('months')->default(1);
                $table->unsignedInteger('amount')->default(0)->comment('实付金额元');
                $table->string('status', 16)->default('paid')->comment('pending/paid/expired/cancelled');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('remark', 255)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('tenant_resource_consume')) {
            Schema::create('tenant_resource_consume', function (Blueprint $table) {
                $table->id();
                $table->date('stat_date');
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('null=平台汇总行');
                $table->string('package_code', 16)->nullable();
                $table->unsignedInteger('proxy_request_count')->default(0)->comment('公共IP请求次数');
                $table->unsignedInteger('ai_call_count')->default(0);
                $table->decimal('ai_token_cost', 12, 2)->default(0)->comment('AI-Token成本元');
                $table->decimal('proxy_ip_cost', 12, 2)->default(0)->comment('公共住宅代理成本元');
                $table->decimal('server_cost', 12, 2)->default(0)->comment('云服务器与对象存储');
                $table->decimal('third_party_cost', 12, 2)->default(0)->comment('指纹/风控第三方');
                $table->decimal('ops_labor_cost', 12, 2)->default(0)->comment('运维人力摊销');
                $table->decimal('revenue', 12, 2)->default(0)->comment('当日归属营收摊销');
                $table->timestamps();
                $table->unique(['stat_date', 'tenant_id']);
                $table->index('stat_date');
            });
        }

        if (!Schema::hasTable('tenant_sub_account')) {
            Schema::create('tenant_sub_account', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id')->nullable()->comment('关联 users.id');
                $table->string('username', 64);
                $table->string('display_name', 64)->nullable();
                $table->string('role', 32)->default('operator')->comment('cs客服/crawler_ops爬虫运维/operator');
                $table->json('permissions')->nullable();
                $table->unsignedTinyInteger('status')->default(1)->comment('1启用0停用');
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'username']);
            });
        }

        if (!Schema::hasTable('industry_prompt')) {
            Schema::create('industry_prompt', function (Blueprint $table) {
                $table->id();
                $table->string('title', 128);
                $table->string('industry', 64)->nullable();
                $table->text('content');
                $table->unsignedTinyInteger('min_package_type')->default(1)
                    ->comment('最低可用套餐：1基础2专业3企业');
                $table->unsignedTinyInteger('template_level')->default(1);
                $table->unsignedTinyInteger('is_published')->default(1);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('proxy_ip_access_logs')) {
            Schema::create('proxy_ip_access_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('proxy_ip_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('crawler_task_id')->nullable();
                $table->string('action', 32)->default('request')->comment('request/check/rotate/risk');
                $table->string('result', 16)->default('ok');
                $table->string('detail', 255)->nullable();
                $table->timestamp('logged_at');
                $table->timestamps();
                $table->index(['proxy_ip_id', 'logged_at']);
                $table->index(['tenant_id', 'logged_at']);
            });
        }

        if (!Schema::hasTable('premium_feature_usage_logs')) {
            Schema::create('premium_feature_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('feature_key', 64);
                $table->string('feature_label', 128)->nullable();
                $table->string('detail', 255)->nullable();
                $table->timestamp('used_at');
                $table->timestamps();
                $table->index(['tenant_id', 'feature_key', 'used_at']);
            });
        }

        if (!Schema::hasTable('crawler_human_behavior')) {
            Schema::create('crawler_human_behavior', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('crawler_task_id')->nullable();
                $table->unsignedInteger('scroll_min_ms')->default(800);
                $table->unsignedInteger('scroll_max_ms')->default(2400);
                $table->unsignedInteger('dwell_min_ms')->default(1500);
                $table->unsignedInteger('dwell_max_ms')->default(5000);
                $table->unsignedInteger('interval_min_ms')->default(3000);
                $table->unsignedInteger('interval_max_ms')->default(8000);
                $table->string('fingerprint_preset', 64)->nullable();
                $table->json('extra')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'crawler_task_id']);
            });
        }

        if (!Schema::hasTable('crm_follow_reminders')) {
            Schema::create('crm_follow_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('crm_lead_id');
                $table->string('title', 128);
                $table->timestamp('remind_at');
                $table->string('status', 16)->default('pending')->comment('pending/done/cancelled');
                $table->string('channel', 32)->default('panel');
                $table->timestamps();
                $table->index(['tenant_id', 'status', 'remind_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_follow_reminders');
        Schema::dropIfExists('crawler_human_behavior');
        Schema::dropIfExists('premium_feature_usage_logs');
        Schema::dropIfExists('proxy_ip_access_logs');
        Schema::dropIfExists('industry_prompt');
        Schema::dropIfExists('tenant_sub_account');
        Schema::dropIfExists('tenant_resource_consume');
        Schema::dropIfExists('tenant_order');

        Schema::table('proxy_ips', function (Blueprint $table) {
            foreach (['pool_type', 'risk_level', 'last_risk_at', 'rotate_group', 'platform_scope'] as $col) {
                if (Schema::hasColumn('proxy_ips', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            foreach (['package_expires_at', 'package_version', 'dedicated_ip_pool_enabled', 'ip_rotate_enabled', 'white_label_title'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('saas_package_setting', function (Blueprint $table) {
            foreach ([
                'price_monthly', 'premium_features', 'enable_sub_account', 'enable_human_behavior',
                'enable_crm_auto_remind', 'enable_excel_export', 'enable_dedicated_ip_pool',
                'enable_ip_risk_check', 'enable_ip_rotate', 'enable_white_label',
            ] as $col) {
                if (Schema::hasColumn('saas_package_setting', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
