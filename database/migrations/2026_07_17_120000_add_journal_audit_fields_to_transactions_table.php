<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('journal_reviewed_at')->nullable()->after('finalized_by');
            $table->foreignId('journal_reviewed_by')->nullable()->after('journal_reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('journal_receipt_checked_at')->nullable()->after('journal_reviewed_by');
            $table->foreignId('journal_receipt_checked_by')->nullable()->after('journal_receipt_checked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_receipt_checked_by');
            $table->dropColumn('journal_receipt_checked_at');
            $table->dropConstrainedForeignId('journal_reviewed_by');
            $table->dropColumn('journal_reviewed_at');
        });
    }
};
