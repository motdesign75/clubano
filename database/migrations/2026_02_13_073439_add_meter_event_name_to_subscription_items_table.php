<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscription_items')) {
            return;
        }

        if (Schema::hasColumn('subscription_items', 'meter_event_name')) {
            return;
        }

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->string('meter_event_name')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_items')) {
            return;
        }

        if (!Schema::hasColumn('subscription_items', 'meter_event_name')) {
            return;
        }

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropColumn('meter_event_name');
        });
    }
};
