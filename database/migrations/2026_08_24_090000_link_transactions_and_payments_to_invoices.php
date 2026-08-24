<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'invoice_id')) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('receipt_meta')
                    ->constrained('invoices')
                    ->nullOnDelete();
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'transaction_id')) {
                $table->foreignId('transaction_id')
                    ->nullable()
                    ->after('invoice_id')
                    ->constrained('transactions')
                    ->nullOnDelete();

                $table->unique(['tenant_id', 'transaction_id'], 'payments_tenant_transaction_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'transaction_id')) {
                $table->dropUnique('payments_tenant_transaction_unique');
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            }
        });
    }
};
