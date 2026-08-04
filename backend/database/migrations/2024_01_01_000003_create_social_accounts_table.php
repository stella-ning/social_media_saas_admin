<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->comment('账号昵称');
            $table->string('uid', 64)->comment('平台UID');
            $table->string('avatar', 255)->nullable();
            $table->string('platform', 32)->comment('小红书/抖音/视频号');
            $table->string('bind_ip', 64)->nullable()->comment('绑定代理IP');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('status', 16)->default('offline')->comment('online/offline');
            $table->text('cookie')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
