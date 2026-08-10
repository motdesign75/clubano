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
            $table->foreignId('created_by')->nullable();
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

            $table->foreign('created_by', 'op_ann_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('operator_announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_announcement_id');
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('email');
            $table->string('status')->default('sent');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['email', 'created_at']);

            $table->foreign('operator_announcement_id', 'op_ann_del_ann_fk')
                ->references('id')
                ->on('operator_announcements')
                ->cascadeOnDelete();
            $table->foreign('tenant_id', 'op_ann_del_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();
            $table->foreign('user_id', 'op_ann_del_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_announcement_deliveries');
        Schema::dropIfExists('operator_announcements');
    }
};
