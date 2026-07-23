<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscriptions')) {
            return;
        }

        // tenant_id hinzufügen
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
        });

        // ===== Backfill nur wenn user_id existiert =====

        if (
            Schema::hasColumn('subscriptions', 'user_id') &&
            Schema::hasColumn('users', 'tenant_id')
        ) {
            $this->backfillTenantIds();
        }

        // ===== FK auf user_id entfernen =====

        if (Schema::hasColumn('subscriptions', 'user_id') && DB::getDriverName() === 'mysql') {

            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'subscriptions'
                AND COLUMN_NAME = 'user_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($foreignKeys as $fk) {
                DB::statement("
                    ALTER TABLE subscriptions
                    DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}
                ");
            }
        }

        // ===== alten Index löschen =====

        if ($this->indexExists('subscriptions', 'subscriptions_user_id_stripe_status_index')) {

            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex('subscriptions_user_id_stripe_status_index');
            });
        }

        // ===== user_id löschen =====

        if (Schema::hasColumn('subscriptions', 'user_id')) {

            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }

        // ===== neuen Index =====

        if (!$this->indexExists('subscriptions', 'subscriptions_tenant_id_stripe_status_index')) {

            Schema::table('subscriptions', function (Blueprint $table) {

                $table->index(
                    ['tenant_id', 'stripe_status'],
                    'subscriptions_tenant_id_stripe_status_index'
                );
            });
        }

        // ===== FK tenant =====

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        try {

            Schema::table('subscriptions', function (Blueprint $table) {

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->nullOnDelete();

            });

        } catch (\Throwable $e) {
        }
    }


    public function down(): void
    {
        if (!Schema::hasTable('subscriptions')) {
            return;
        }

        if ($this->indexExists('subscriptions', 'subscriptions_tenant_id_stripe_status_index')) {

            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex('subscriptions_tenant_id_stripe_status_index');
            });
        }

        if (Schema::hasColumn('subscriptions', 'tenant_id')) {

            try {
                Schema::table('subscriptions', function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                });
            } catch (\Throwable $e) {}

            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        if (!Schema::hasColumn('subscriptions', 'user_id')) {

            Schema::table('subscriptions', function (Blueprint $table) {

                $table->unsignedBigInteger('user_id')->after('id');

                $table->index(
                    ['user_id', 'stripe_status'],
                    'subscriptions_user_id_stripe_status_index'
                );
            });
        }
    }


    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return collect(Schema::getIndexes($table))
                ->contains(fn ($index) => ($index['name'] ?? null) === $indexName);
        }

        $dbName = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function backfillTenantIds(): void
    {
        DB::table('subscriptions')
            ->whereNull('tenant_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) {
                $tenantIds = DB::table('users')
                    ->whereIn('id', $subscriptions->pluck('user_id')->filter()->unique())
                    ->pluck('tenant_id', 'id');

                foreach ($subscriptions as $subscription) {
                    $tenantId = $tenantIds[$subscription->user_id] ?? null;

                    if ($tenantId) {
                        DB::table('subscriptions')
                            ->where('id', $subscription->id)
                            ->update(['tenant_id' => $tenantId]);
                    }
                }
            });
    }
};
