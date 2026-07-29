<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_booking_participants', function (Blueprint $table) {
            $table->string('organization_name')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('event_booking_participants', function (Blueprint $table) {
            $table->dropColumn('organization_name');
        });
    }
};
