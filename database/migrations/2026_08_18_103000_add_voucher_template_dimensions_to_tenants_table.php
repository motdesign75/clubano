<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'voucher_template_width')) {
                $table->unsignedInteger('voucher_template_width')->nullable()->after('voucher_template_path');
            }

            if (! Schema::hasColumn('tenants', 'voucher_template_height')) {
                $table->unsignedInteger('voucher_template_height')->nullable()->after('voucher_template_width');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach ([
                'voucher_template_height',
                'voucher_template_width',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
