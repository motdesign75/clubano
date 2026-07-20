<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'tenant_id')) {
                $table->uuid('tenant_id')->after('id');
            }

            if (!Schema::hasColumn('transactions', 'account_from_id')) {
                $table->foreignId('account_from_id')->constrained('accounts')->after('amount');
            }

            if (!Schema::hasColumn('transactions', 'account_to_id')) {
                $table->foreignId('account_to_id')->constrained('accounts')->after('account_from_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'account_from_id')) {
                $table->dropForeign(['account_from_id']);
                $table->dropColumn('account_from_id');
            }

            if (Schema::hasColumn('transactions', 'account_to_id')) {
                $table->dropForeign(['account_to_id']);
                $table->dropColumn('account_to_id');
            }

            if (Schema::hasColumn('transactions', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};
