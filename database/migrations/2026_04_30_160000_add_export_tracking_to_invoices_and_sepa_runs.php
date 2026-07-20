<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'sepa_exported_at')) {
                $table->timestamp('sepa_exported_at')->nullable()->after('paid_at');
            }

            if (!Schema::hasColumn('invoices', 'sepa_sequence_type')) {
                $table->string('sepa_sequence_type', 10)->nullable()->after('sepa_exported_at');
            }

            if (!Schema::hasColumn('invoices', 'last_sepa_run_id')) {
                $table->foreignId('last_sepa_run_id')->nullable()->after('sepa_sequence_type')->constrained('sepa_runs')->nullOnDelete();
            }
        });

        Schema::table('sepa_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('sepa_runs', 'file_path')) {
                $table->string('file_path')->nullable()->after('file_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'last_sepa_run_id')) {
                $table->dropConstrainedForeignId('last_sepa_run_id');
            }

            $columns = [];
            foreach (['sepa_exported_at', 'sepa_sequence_type'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('sepa_runs', function (Blueprint $table) {
            if (Schema::hasColumn('sepa_runs', 'file_path')) {
                $table->dropColumn('file_path');
            }
        });
    }
};
