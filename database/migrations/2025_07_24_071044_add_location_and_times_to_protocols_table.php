<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            $table->string('location')->nullable()->after('type');
            $table->time('start_time')->nullable()->after('location');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            $table->dropColumn(['location', 'start_time', 'end_time']);
        });
    }
};
