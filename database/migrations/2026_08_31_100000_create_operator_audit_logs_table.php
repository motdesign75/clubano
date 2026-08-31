<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->unsignedBigInteger('target_tenant_id')->nullable()->index();
            $table->string('target_tenant_name')->nullable();
            $table->string('target_tenant_email')->nullable();
            $table->string('action')->index();
            $table->string('label')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_tenant_id', 'created_at'], 'operator_audit_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_audit_logs');
    }
};
