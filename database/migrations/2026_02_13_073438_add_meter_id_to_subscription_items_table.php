<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wenn die Tabelle nicht existiert, nichts tun (Altbestand / anderes Setup)
        if (!Schema::hasTable('subscription_items')) {
            return;
        }

        // Wenn meter_id bereits existiert, nichts tun
        if (Schema::hasColumn('subscription_items', 'meter_id')) {
            return;
        }

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->string('meter_id')->nullable()->after('stripe_price');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_items')) {
            return;
        }

        if (!Schema::hasColumn('subscription_items', 'meter_id')) {
            return;
        }

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropColumn('meter_id');
        });
    }
};
