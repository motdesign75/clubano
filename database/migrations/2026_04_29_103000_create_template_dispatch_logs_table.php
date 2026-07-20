<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('template_dispatch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 24);
            $table->string('action', 24);
            $table->string('recipient_type', 24);
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_reference')->nullable();
            $table->string('subject')->nullable();
            $table->text('message_excerpt')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'dispatched_at']);
            $table->index(['tenant_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_dispatch_logs');
    }
};
