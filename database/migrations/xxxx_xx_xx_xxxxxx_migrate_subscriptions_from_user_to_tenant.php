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
            Schema::hasTable('users') &&
            Schema::hasColumn('users', 'tenant_id')
        ) {

            DB::statement("
                UPDATE subscriptions
                JOIN users ON users.id = subscriptions.user_id
                SET subscriptions.tenant_id = users.tenant_id
                WHERE subscriptions.tenant_id IS NULL
            ");
        }

        // ===== FK auf user_id entfernen =====

        if (Schema::hasColumn('subscriptions', 'user_id')) {

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

            DB::statement("
                ALTER TABLE subscriptions
                DROP INDEX subscriptions_user_id_stripe_status_index
            ");
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

            DB::statement("
                ALTER TABLE subscriptions
                DROP INDEX subscriptions_tenant_id_stripe_status_index
            ");
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
        $dbName = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};