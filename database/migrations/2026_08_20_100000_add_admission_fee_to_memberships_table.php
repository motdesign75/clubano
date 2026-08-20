<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            if (! Schema::hasColumn('memberships', 'admission_fee')) {
                $table->decimal('admission_fee', 8, 2)->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            if (Schema::hasColumn('memberships', 'admission_fee')) {
                $table->dropColumn('admission_fee');
            }
        });
    }
};
