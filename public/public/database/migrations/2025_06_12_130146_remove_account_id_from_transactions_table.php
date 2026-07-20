<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'account_id')) {
                // Erst Foreign Key löschen, dann Spalte
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained('accounts');
        });
    }
};
