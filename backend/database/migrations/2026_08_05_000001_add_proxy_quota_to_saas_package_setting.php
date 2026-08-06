<?php

/**
 * 套餐权限：代理 IP 配额管控字段
 * - max_proxy_ip：最大绑定代理 IP 数量（null=无限）
 * - daily_proxy_request_limit：每日 IP 成功请求次数上限（null=无限）
 * - allow_self_proxy：是否允许租户配置自有代理 IP
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_package_setting', function (Blueprint $table) {
            $table->unsignedInteger('max_proxy_ip')->nullable()->after('max_social_account')
                ->comment('最大绑定代理IP数量，null=无限');
            $table->unsignedInteger('daily_proxy_request_limit')->nullable()->after('max_proxy_ip')
                ->comment('每日代理IP成功请求次数上限，null=无限');
            $table->unsignedTinyInteger('allow_self_proxy')->default(0)->after('daily_proxy_request_limit')
                ->comment('是否允许租户配置自有代理IP');
        });

        // 基础版：关闭自有IP，有限配额
        DB::table('saas_package_setting')->where('package_type', 1)->update([
            'max_proxy_ip' => 3,
            'daily_proxy_request_limit' => 500,
            'allow_self_proxy' => 0,
            'updated_at' => now(),
        ]);

        // 专业版：开启自有IP
        DB::table('saas_package_setting')->where('package_type', 2)->update([
            'max_proxy_ip' => 15,
            'daily_proxy_request_limit' => 5000,
            'allow_self_proxy' => 1,
            'updated_at' => now(),
        ]);

        // 企业版：开启自有IP，无上限
        DB::table('saas_package_setting')->where('package_type', 3)->update([
            'max_proxy_ip' => null,
            'daily_proxy_request_limit' => null,
            'allow_self_proxy' => 1,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('saas_package_setting', function (Blueprint $table) {
            $table->dropColumn(['max_proxy_ip', 'daily_proxy_request_limit', 'allow_self_proxy']);
        });
    }
};
