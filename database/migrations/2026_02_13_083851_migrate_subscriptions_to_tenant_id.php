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

        // 1) tenant_id hinzufügen (nullable für Backfill)
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
        });

        // 2) Backfill tenant_id aus users.tenant_id, sofern user_id existiert
        if (Schema::hasColumn('subscriptions', 'user_id') && Schema::hasColumn('users', 'tenant_id')) {
            DB::table('subscriptions')
                ->join('users', 'users.id', '=', 'subscriptions.user_id')
                ->whereNull('subscriptions.tenant_id')
                ->update([
                    'subscriptions.tenant_id' => DB::raw('users.tenant_id'),
                ]);
        }

        // 3) Alten Index (user_id, stripe_status) droppen, falls er existiert – Name dynamisch ermitteln
        $oldIndex = $this->findIndexName('subscriptions', ['user_id', 'stripe_status']);
        if ($oldIndex) {
            Schema::table('subscriptions', function (Blueprint $table) use ($oldIndex) {
                $table->dropIndex($oldIndex);
            });
        }

        // 4) user_id entfernen, wenn vorhanden
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });

        // 5) Neuen Index (tenant_id, stripe_status) anlegen, falls nicht vorhanden
        $newIndex = $this->findIndexName('subscriptions', ['tenant_id', 'stripe_status']);
        if (!$newIndex) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['tenant_id', 'stripe_status'], 'subscriptions_tenant_id_stripe_status_index');
            });
        }

        // 6) Optional: Foreign Key auf tenants (wenn Tabelle existiert)
        if (Schema::hasTable('tenants')) {
            // FK nur hinzufügen, wenn noch keiner existiert
            $fkExists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'subscriptions')
                ->where('COLUMN_NAME', 'tenant_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->exists();

            if (!$fkExists) {
                Schema::table('subscriptions', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // bewusst kein Rückbau
    }

    private function findIndexName(string $table, array $columns): ?string
    {
        $db = DB::getDatabaseName();

        $rows = DB::table('information_schema.STATISTICS')
            ->select('INDEX_NAME', DB::raw('GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as cols'))
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->groupBy('INDEX_NAME')
            ->get();

        $needle = implode(',', $columns);

        foreach ($rows as $row) {
            if ($row->cols === $needle) {
                return $row->INDEX_NAME;
            }
        }

        return null;
    }
};
