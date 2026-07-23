<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            if (! Schema::hasColumn('protocols', 'raw_agenda')) {
                $table->longText('raw_agenda')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            if (Schema::hasColumn('protocols', 'raw_agenda')) {
                $table->dropColumn('raw_agenda');
            }
        });
    }
};
