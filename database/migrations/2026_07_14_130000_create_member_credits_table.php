<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description');
            $table->text('notes')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2);
            $table->date('credited_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'member_id']);
            $table->index(['tenant_id', 'remaining_amount']);
        });

        Schema::create('member_credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_credit_id')->constrained('member_credits')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'member_credit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_credit_applications');
        Schema::dropIfExists('member_credits');
    }
};
