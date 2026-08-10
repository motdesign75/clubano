<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'chart_name')) {
                $table->string('chart_name')->nullable()->after('tax_area');
            }

            if (! Schema::hasColumn('accounts', 'tax_key')) {
                $table->string('tax_key', 40)->nullable()->after('chart_name');
            }

            if (! Schema::hasColumn('accounts', 'is_postable')) {
                $table->boolean('is_postable')->default(true)->after('tax_key');
            }

            if (! Schema::hasColumn('accounts', 'datev_automatic')) {
                $table->boolean('datev_automatic')->default(false)->after('is_postable');
            }

            if (! Schema::hasColumn('accounts', 'import_source')) {
                $table->string('import_source')->nullable()->after('datev_automatic');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            foreach (['chart_name', 'tax_key', 'is_postable', 'datev_automatic', 'import_source'] as $column) {
                if (Schema::hasColumn('accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
