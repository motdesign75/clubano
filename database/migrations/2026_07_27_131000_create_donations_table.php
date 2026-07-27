<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificate_number')->nullable();
            $table->string('status')->default('draft');
            $table->string('kind')->default('money');
            $table->date('donated_at');
            $table->decimal('amount', 12, 2);
            $table->string('purpose')->nullable();
            $table->string('donor_name');
            $table->string('donor_email')->nullable();
            $table->string('donor_street')->nullable();
            $table->string('donor_zip')->nullable();
            $table->string('donor_city')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'donated_at']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'certificate_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
