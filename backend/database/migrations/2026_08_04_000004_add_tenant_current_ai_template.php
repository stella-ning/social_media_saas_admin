<?php

/**
 * 租户当前启用 AI 参数模板 + 模板套餐等级
 * - tenants.current_ai_param_template_id：租户选定启用的模板
 * - saas_ai_param_template.template_level：1基础 / 2专业 / 3企业（套餐下拉筛选用）
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_ai_param_template', function (Blueprint $table) {
            $table->unsignedTinyInteger('template_level')
                ->default(1)
                ->after('is_default')
                ->comment('1=基础版可用 2=专业版可用 3=企业版可用');
            $table->index(['tenant_id', 'template_level']);
        });

        // 实际表名为 tenants（需求文档中的 saas_tenant）
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('current_ai_param_template_id')
                ->nullable()
                ->after('remark')
                ->comment('租户当前启用的 AI 参数模板');
            $table->index('current_ai_param_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['current_ai_param_template_id']);
            $table->dropColumn('current_ai_param_template_id');
        });

        Schema::table('saas_ai_param_template', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'template_level']);
            $table->dropColumn('template_level');
        });
    }
};
