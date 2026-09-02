<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            if (! Schema::hasColumn('public_forms', 'booking_address_tone')) {
                $table->string('booking_address_tone', 10)->default('du')->after('success_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            if (Schema::hasColumn('public_forms', 'booking_address_tone')) {
                $table->dropColumn('booking_address_tone');
            }
        });
    }
};
