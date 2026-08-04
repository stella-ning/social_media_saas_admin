<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('category', 64);
            $table->string('tag_type', 16)->nullable();
            $table->string('name', 128);
            $table->string('desc', 255)->nullable();
            $table->text('role')->nullable();
            $table->text('rules')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('knowledge_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('size', 32)->nullable();
            $table->string('status', 16)->default('processing');
            $table->string('tags', 128)->nullable();
            $table->string('icon_color', 16)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_docs');
        Schema::dropIfExists('ai_prompt_templates');
    }
};
