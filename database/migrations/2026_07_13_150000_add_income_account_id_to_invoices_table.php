<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'income_account_id')) {
                $table->foreignId('income_account_id')
                    ->nullable()
                    ->after('contact_id')
                    ->constrained('accounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'income_account_id')) {
                $table->dropForeign(['income_account_id']);
                $table->dropColumn('income_account_id');
            }
        });
    }
};
