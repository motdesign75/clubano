<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('max_participants_total')->nullable()->after('max_participants_per_booking');
            $table->unsignedInteger('min_participants')->nullable()->after('max_participants_total');
            $table->dateTime('registration_deadline')->nullable()->after('min_participants');
            $table->boolean('show_remaining_spots')->default(false)->after('registration_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'max_participants_total',
                'min_participants',
                'registration_deadline',
                'show_remaining_spots',
            ]);
        });
    }
};
