<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('import_runs', 'import_type')) {
                $table->string('import_type')->default('members')->after('tenant_id');
            }

            if (! Schema::hasColumn('import_runs', 'skipped_count')) {
                $table->unsignedInteger('skipped_count')->default(0)->after('imported_count');
            }

            if (! Schema::hasColumn('import_runs', 'summary')) {
                $table->json('summary')->nullable()->after('skipped_count');
            }
        });

        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'import_run_id')) {
                $table->foreignId('import_run_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('import_runs')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'import_run_id')) {
                $table->dropConstrainedForeignId('import_run_id');
            }
        });

        Schema::table('import_runs', function (Blueprint $table) {
            foreach (['summary', 'skipped_count', 'import_type'] as $column) {
                if (Schema::hasColumn('import_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
