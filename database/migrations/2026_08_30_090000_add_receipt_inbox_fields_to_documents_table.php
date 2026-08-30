<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_booking_receipt')->default(false)->after('invoice_id');
            $table->string('receipt_status', 30)->default('not_relevant')->after('is_booking_receipt');
            $table->decimal('recognized_amount', 12, 2)->nullable()->after('receipt_status');
            $table->string('recognized_currency', 3)->nullable()->after('recognized_amount');
            $table->date('recognized_date')->nullable()->after('recognized_currency');
            $table->string('recognized_vendor')->nullable()->after('recognized_date');
            $table->string('recognized_invoice_number')->nullable()->after('recognized_vendor');
            $table->string('recognition_source')->nullable()->after('recognized_invoice_number');
            $table->text('recognition_notes')->nullable()->after('recognition_source');
            $table->unsignedBigInteger('linked_transaction_id')->nullable()->after('recognition_notes');

            $table->index(['tenant_id', 'is_booking_receipt', 'receipt_status'], 'docs_receipt_inbox_idx');
            $table->index(['tenant_id', 'linked_transaction_id'], 'docs_receipt_trx_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('docs_receipt_inbox_idx');
            $table->dropIndex('docs_receipt_trx_idx');
            $table->dropColumn([
                'is_booking_receipt',
                'receipt_status',
                'recognized_amount',
                'recognized_currency',
                'recognized_date',
                'recognized_vendor',
                'recognized_invoice_number',
                'recognition_source',
                'recognition_notes',
                'linked_transaction_id',
            ]);
        });
    }
};
