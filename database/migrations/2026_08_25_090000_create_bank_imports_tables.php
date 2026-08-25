<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('format', 20);
            $table->string('status')->default('uploaded');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('booked_count')->default(0);
            $table->date('statement_from')->nullable();
            $table->date('statement_to')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at'], 'bank_imports_tenant_created_idx');
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('selected_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('booking_date');
            $table->date('value_date')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('direction', 10);
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_iban')->nullable();
            $table->text('purpose')->nullable();
            $table->string('end_to_end_id')->nullable();
            $table->string('bank_reference')->nullable();
            $table->string('fingerprint', 64);
            $table->string('status')->default('pending');
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'fingerprint'], 'bank_transactions_tenant_fingerprint_unique');
            $table->index(['tenant_id', 'status', 'booking_date'], 'bank_transactions_tenant_status_date_idx');
            $table->index(['bank_import_id', 'status'], 'bank_transactions_import_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_imports');
    }
};
