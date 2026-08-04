<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 爬虫任务强制绑定社媒账号，运行时使用该账号专属代理 IP（不可更换）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawler_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('social_account_id')->nullable()->after('tenant_id')->comment('绑定社媒账号，强制使用其专属代理');
            $table->index('social_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('crawler_tasks', function (Blueprint $table) {
            $table->dropIndex(['social_account_id']);
            $table->dropColumn('social_account_id');
        });
    }
};
