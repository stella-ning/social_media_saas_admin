<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('nickname', 64);
            $table->string('phone', 32)->nullable();
            $table->string('quote', 255)->nullable();
            $table->string('channel', 32)->nullable();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->string('intent', 16)->default('low')->comment('high/mid/low');
            $table->string('status', 16)->default('未处理');
            $table->string('follower', 64)->nullable();
            $table->json('tags')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'intent', 'status']);
            $table->index('nickname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
