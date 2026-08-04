<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('avatar', 255)->nullable();
            $table->string('platform', 32)->nullable();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('last_msg', 255)->nullable();
            $table->string('time_label', 32)->nullable();
            $table->unsignedInteger('unread')->default(0);
            $table->string('date_label', 64)->nullable();
            $table->boolean('ai_auto_reply')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('message_sessions')->cascadeOnDelete();
            $table->string('from_type', 16)->comment('user/ai/human');
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32)->default('basic');
            $table->string('key', 64)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_sessions');
    }
};
