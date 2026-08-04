<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawler_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('platform', 32);
            $table->string('task_type', 32)->default('keyword')->comment('keyword/monitor');
            $table->text('keywords')->nullable();
            $table->string('target', 255)->nullable()->comment('展示：关键词/监控对象');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('frequency', 32)->default('每2小时');
            $table->string('status', 16)->default('running')->comment('running/paused');
            $table->unsignedInteger('today_count')->default(0);
            $table->unsignedInteger('daily_limit')->default(500);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'platform']);
        });

        Schema::create('crawler_task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('crawler_tasks')->cascadeOnDelete();
            $table->string('type', 16)->default('primary');
            $table->string('content', 500);
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawler_task_logs');
        Schema::dropIfExists('crawler_tasks');
    }
};
