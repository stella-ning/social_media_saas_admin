<?php

/**
 * 租户表增加代理 IP 配额（租户级覆盖，与套餐配置弹窗一致）
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('max_proxy_ip')->nullable()->after('kb')
                ->comment('最大绑定代理IP数，null=跟随套餐/无限');
            $table->unsignedInteger('daily_proxy_request_limit')->nullable()->after('max_proxy_ip')
                ->comment('每日代理成功请求上限，null=跟随套餐/无限');
            $table->unsignedTinyInteger('allow_self_proxy')->nullable()->after('daily_proxy_request_limit')
                ->comment('是否允许自有代理：null跟随套餐 0关 1开');
        });

        // 按现有套餐回填默认值
        $map = [
            'basic' => ['max_proxy_ip' => 3, 'daily_proxy_request_limit' => 500, 'allow_self_proxy' => 0],
            'pro' => ['max_proxy_ip' => 15, 'daily_proxy_request_limit' => 5000, 'allow_self_proxy' => 1],
            'ent' => ['max_proxy_ip' => null, 'daily_proxy_request_limit' => null, 'allow_self_proxy' => 1],
        ];
        foreach ($map as $pkg => $vals) {
            DB::table('tenants')->where('package', $pkg)->update(array_merge($vals, ['updated_at' => now()]));
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['max_proxy_ip', 'daily_proxy_request_limit', 'allow_self_proxy']);
        });
    }
};
