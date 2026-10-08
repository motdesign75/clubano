<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('payable_status', 30)->nullable()->after('receipt_status');
            $table->decimal('payable_paid_amount', 12, 2)->nullable()->after('recognized_amount');
            $table->date('payable_due_date')->nullable()->after('recognized_date');
            $table->string('payable_due_source', 30)->nullable()->after('payable_due_date');
            $table->text('payable_due_note')->nullable()->after('payable_due_source');
            $table->string('payable_iban', 34)->nullable()->after('recognized_invoice_number');
            $table->string('payable_reference')->nullable()->after('payable_iban');

            $table->index(['tenant_id', 'is_booking_receipt', 'payable_status'], 'docs_payable_status_idx');
            $table->index(['tenant_id', 'payable_due_date'], 'docs_payable_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('docs_payable_status_idx');
            $table->dropIndex('docs_payable_due_idx');
            $table->dropColumn([
                'payable_status',
                'payable_paid_amount',
                'payable_due_date',
                'payable_due_source',
                'payable_due_note',
                'payable_iban',
                'payable_reference',
            ]);
        });
    }
};
