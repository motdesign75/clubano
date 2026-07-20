<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Cashier erwartet "name" statt "type"
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'type') && !Schema::hasColumn('subscriptions', 'name')) {
                $table->renameColumn('type', 'name');
            }
        });

        // 2) Tenant-Billing: Cashier erwartet FK "tenant_id"
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
        });

        // 3) Index nur anlegen, wenn er noch nicht existiert
        $indexName = 'subscriptions_tenant_id_stripe_status_index';

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'subscriptions')
            ->where('index_name', $indexName)
            ->exists();

        if (!$exists) {
            Schema::table('subscriptions', function (Blueprint $table) use ($indexName) {
                // expliziter Name, damit wir ihn später sauber prüfen/entfernen können
                $table->index(['tenant_id', 'stripe_status'], $indexName);
            });
        }

        // 4) Backfill (falls Subscriptions bereits existieren und tenant_id leer ist)
        if (Schema::hasColumn('subscriptions', 'user_id') && Schema::hasTable('users')) {
            DB::statement("
                UPDATE subscriptions s
                JOIN users u ON u.id = s.user_id
                SET s.tenant_id = u.tenant_id
                WHERE s.tenant_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        $indexName = 'subscriptions_tenant_id_stripe_status_index';

        // Index nur droppen, wenn er existiert
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'subscriptions')
            ->where('index_name', $indexName)
            ->exists();

        if ($exists) {
            Schema::table('subscriptions', function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }

            if (Schema::hasColumn('subscriptions', 'name') && !Schema::hasColumn('subscriptions', 'type')) {
                $table->renameColumn('name', 'type');
            }
        });
    }
};
