<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'organization_bookings_free')) {
                $table->boolean('organization_bookings_free')->default(false)->after('member_price_per_person');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'organization_bookings_free')) {
                $table->dropColumn('organization_bookings_free');
            }
        });
    }
};
