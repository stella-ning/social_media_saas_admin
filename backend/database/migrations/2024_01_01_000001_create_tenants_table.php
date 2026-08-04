<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 租户表
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('租户名称');
            $table->string('contact', 64)->comment('联系人');
            $table->string('phone', 32)->nullable()->comment('联系电话');
            $table->string('email', 128)->nullable()->comment('邮箱');
            $table->string('package', 16)->default('basic')->comment('套餐 basic/pro/ent');
            $table->tinyInteger('status')->default(1)->comment('1启用 0禁用');
            $table->unsignedInteger('concurrent')->default(5)->comment('任务并发数');
            $table->unsignedInteger('ai_quota')->default(1000)->comment('AI调用额度/月');
            $table->unsignedInteger('binds')->default(5)->comment('账号绑定上限');
            $table->decimal('kb', 8, 1)->default(1)->comment('知识库容量GB');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'package']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
