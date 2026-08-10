<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body_markdown');
            $table->longText('body_html');
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('recipient_filter')->default('all_active');
            $table->json('recipient_summary')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('operator_announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('email');
            $table->string('status')->default('sent');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_announcement_deliveries');
        Schema::dropIfExists('operator_announcements');
    }
};
