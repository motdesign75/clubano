<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {

            // Falls du "tenant_id" hast, aber kein "user_id": tenant_id -> user_id
            if (Schema::hasColumn('subscriptions', 'tenant_id') && !Schema::hasColumn('subscriptions', 'user_id')) {
                $table->renameColumn('tenant_id', 'user_id');
            }

            // Falls beide existieren: Daten rüberkopieren und später tenant_id entfernen
            if (Schema::hasColumn('subscriptions', 'tenant_id') && Schema::hasColumn('subscriptions', 'user_id')) {
                DB::statement('UPDATE subscriptions SET user_id = tenant_id WHERE user_id IS NULL');
            }
        });

        // (Optional) tenant_id drop, falls noch vorhanden
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });

        // Index sicherstellen (Cashier nutzt oft user_id + stripe_status)
        Schema::table('subscriptions', function (Blueprint $table) {
            // Index ggf. neu setzen (wenn er fehlt)
            // Achtung: index-Name kann variieren, deshalb einfach neu anlegen mit neuem Namen:
            $table->index(['user_id', 'stripe_status'], 'subscriptions_user_id_stripe_status_index');
        });
    }

    public function down(): void
    {
        // Rückbau (nur falls nötig)
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'user_id') && !Schema::hasColumn('subscriptions', 'tenant_id')) {
                $table->renameColumn('user_id', 'tenant_id');
            }
        });
    }
};
