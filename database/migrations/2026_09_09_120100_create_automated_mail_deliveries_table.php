<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automated_mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automated_mail_setting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('occasion', 80);
            $table->date('occasion_date');
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->string('status', 40)->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'occasion', 'member_id', 'occasion_date'], 'automated_mail_unique_member_occasion');
            $table->index(['tenant_id', 'occasion', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automated_mail_deliveries');
    }
};
