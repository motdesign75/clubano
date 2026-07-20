<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {

            // Cashier erwartet diese Felder (je nach Version). Wir fügen nur hinzu, wenn sie fehlen.

            if (!Schema::hasColumn('subscriptions', 'type')) {
                $table->string('type')->default('default')->after('stripe_id');
            }

            if (!Schema::hasColumn('subscriptions', 'stripe_status')) {
                $table->string('stripe_status')->nullable()->after('type');
            }

            // In neueren Cashier-Versionen heißt es stripe_price (nicht stripe_plan).
            if (!Schema::hasColumn('subscriptions', 'stripe_price')) {
                $table->string('stripe_price')->nullable()->after('stripe_status');
            }

            if (!Schema::hasColumn('subscriptions', 'quantity')) {
                $table->integer('quantity')->nullable()->after('stripe_price');
            }

            if (!Schema::hasColumn('subscriptions', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('subscriptions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('trial_ends_at');
            }
        });
    }

    public function down(): void
    {
        // absichtlich leer – wir wollen hier nichts automatisch zurückbauen
    }
};
