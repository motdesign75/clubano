<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Cashier erwartet "type" (bei dir wird "default" geschrieben)
            if (! Schema::hasColumn('subscriptions', 'type')) {
                $table->string('type')->default('default')->after('stripe_id');
                // In vielen Setups ist (billable_id, type) eindeutig sinnvoll,
                // aber das hängt davon ab, ob du mehrere Subscriptions pro User zulässt.
                // Index nur setzen, wenn du nicht schon passende Indizes hast.
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
