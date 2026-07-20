<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $wrongIndexName = 'subscriptions_tenant_id_stripe_status_index';

        // A) Falls der "tenant_id Index" aktuell fälschlich auf user_id liegt:
        //    Wir droppen den Index-NAMEN, damit wir ihn korrekt neu anlegen können.
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'subscriptions')
            ->where('index_name', $wrongIndexName)
            ->exists();

        if ($exists) {
            Schema::table('subscriptions', function (Blueprint $table) use ($wrongIndexName) {
                $table->dropIndex($wrongIndexName);
            });
        }

        // B) tenant_id Spalte anlegen, wenn sie fehlt
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
        });

        // C) Backfill tenant_id
        //    - Wenn es Subscriptions gibt, die user_id gesetzt haben:
        //      subscriptions.user_id -> users.tenant_id -> subscriptions.tenant_id
        if (Schema::hasColumn('subscriptions', 'user_id') && Schema::hasTable('users')) {
            DB::statement("
                UPDATE subscriptions s
                JOIN users u ON u.id = s.user_id
                SET s.tenant_id = u.tenant_id
                WHERE s.tenant_id IS NULL
            ");
        }

        // D) Jetzt den Index korrekt auf tenant_id + stripe_status anlegen
        Schema::table('subscriptions', function (Blueprint $table) use ($wrongIndexName) {
            $table->index(['tenant_id', 'stripe_status'], $wrongIndexName);
        });

        // E) Optional: user_id + stripe_status Index (mit anderem Namen!)
        //    Falls du den brauchst, kannst du ihn wieder anlegen.
        $userIndexName = 'subscriptions_user_id_stripe_status_index';

        $userIndexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'subscriptions')
            ->where('index_name', $userIndexName)
            ->exists();

        if (!$userIndexExists && Schema::hasColumn('subscriptions', 'user_id')) {
            Schema::table('subscriptions', function (Blueprint $table) use ($userIndexName) {
                $table->index(['user_id', 'stripe_status'], $userIndexName);
            });
        }

        // F) Cashier: name statt type (falls noch nicht gefixt)
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'type') && !Schema::hasColumn('subscriptions', 'name')) {
                $table->renameColumn('type', 'name');
            }
        });
    }

    public function down(): void
    {
        $tenantIndexName = 'subscriptions_tenant_id_stripe_status_index';
        $userIndexName   = 'subscriptions_user_id_stripe_status_index';

        $tenantIndexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'subscriptions')
            ->where('index_name', $tenantIndexName)
            ->exists();

        if ($tenantIndexExists) {
            Schema::table('subscriptions', function (Blueprint $table) use ($tenantIndexName) {
                $table->dropIndex($tenantIndexName);
            });
        }

        $userIndexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'subscriptions')
            ->where('index_name', $userIndexName)
            ->exists();

        if ($userIndexExists) {
            Schema::table('subscriptions', function (Blueprint $table) use ($userIndexName) {
                $table->dropIndex($userIndexName);
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
