<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'letter_bottom_margin_mm')) {
                $table->unsignedTinyInteger('letter_bottom_margin_mm')->default(30)->after('use_letterhead');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'letter_bottom_margin_mm')) {
                $table->dropColumn('letter_bottom_margin_mm');
            }
        });
    }
};
