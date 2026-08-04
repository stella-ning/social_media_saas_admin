<?php

/**
 * 社媒账号自动登录相关表
 * - saas_social_account：账号主表（AES 加密密码、一号一 IP、固定浏览器指纹）
 * - saas_account_cookie：Cookie 独立存储
 * - saas_tenant_proxy：租户与代理 IP 分配关联（租户隔离）
 * - saas_account_operation_logs：操作/风控日志（不含敏感明文）
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- 租户-代理IP 分配关联表 ----------
        Schema::create('saas_tenant_proxy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('proxy_ip_id')->constrained('proxy_ips')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'proxy_ip_id']);
            $table->index('proxy_ip_id');
        });

        // ---------- 社媒账号主表 ----------
        Schema::create('saas_social_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete()->comment('所属租户');
            $table->unsignedTinyInteger('platform')->comment('1=小红书 2=抖音 3=视频号');
            $table->string('account_name', 128)->comment('登录账号/手机号');
            $table->text('encrypt_pwd')->comment('AES-256 加密后的密码，禁止明文落地');
            $table->foreignId('bind_proxy_id')->nullable()->constrained('proxy_ips')->nullOnDelete()->comment('绑定代理IP主键');
            $table->string('browser_user_agent', 512)->nullable()->comment('固定浏览器指纹 UA');
            $table->string('browser_viewport', 32)->default('1920x1080')->comment('固定分辨率');
            $table->unsignedTinyInteger('account_status')->default(0)->comment('0离线 1在线');
            $table->timestamp('last_refresh_cookie')->nullable()->comment('最近一次刷新Cookie时间');
            $table->unsignedTinyInteger('login_fail_count')->default(0)->comment('连续登录失败次数');
            $table->string('risk_tip', 255)->nullable()->comment('风控/风险提示');
            $table->string('display_name', 128)->nullable()->comment('展示昵称');
            $table->string('avatar', 255)->nullable();
            $table->timestamps();

            // 一号一IP：同一时刻一个代理只能绑一个账号
            $table->unique('bind_proxy_id');
            $table->index(['tenant_id', 'platform', 'account_status']);
            $table->unique(['tenant_id', 'platform', 'account_name'], 'uniq_tenant_platform_account');
        });

        // ---------- Cookie 存储表 ----------
        Schema::create('saas_account_cookie', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('social_account_id')->comment('关联 saas_social_account.id');
            $table->longText('cookie_json')->comment('完整 Cookie JSON');
            $table->unsignedTinyInteger('expire_status')->default(1)->comment('0失效 1可用');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('social_account_id', 'fk_cookie_social_account')
                ->references('id')
                ->on('saas_social_account')
                ->cascadeOnDelete();
            $table->index(['social_account_id', 'expire_status']);
        });

        // ---------- 操作日志（不含账号密码 Cookie 明文） ----------
        Schema::create('saas_account_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('social_account_id');
            $table->string('type', 16)->default('info')->comment('success/warning/danger/info');
            $table->string('content', 500);
            $table->timestamp('logged_at')->useCurrent();

            $table->foreign('social_account_id', 'fk_oplog_social_account')
                ->references('id')
                ->on('saas_social_account')
                ->cascadeOnDelete();
            $table->index('social_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_account_operation_logs');
        Schema::dropIfExists('saas_account_cookie');
        Schema::dropIfExists('saas_social_account');
        Schema::dropIfExists('saas_tenant_proxy');
    }
};
