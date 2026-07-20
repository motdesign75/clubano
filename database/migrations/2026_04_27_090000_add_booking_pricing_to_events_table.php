<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('price_per_person', 10, 2)->default(0)->after('booking_enabled');
            $table->string('currency', 3)->default('EUR')->after('price_per_person');
            $table->unsignedInteger('max_participants_per_booking')->default(1)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'price_per_person',
                'currency',
                'max_participants_per_booking',
            ]);
        });
    }
};
