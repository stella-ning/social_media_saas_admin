<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_ips', function (Blueprint $table) {
            $table->id();
            $table->string('address', 128)->comment('IP:端口');
            $table->string('location', 64)->nullable();
            $table->string('protocol', 32)->default('HTTP/HTTPS');
            $table->string('status', 16)->default('idle')->comment('running/idle/error');
            $table->unsignedInteger('load')->default(0);
            $table->unsignedInteger('capacity')->default(100);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_ips');
    }
};
