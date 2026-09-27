<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_app_users', function (Blueprint $table) {
            if ($this->indexExists('mobile_app_users_tenant_id_username_unique')) {
                $table->dropUnique('mobile_app_users_tenant_id_username_unique');
            }

            if (! $this->indexExists('mobile_app_users_username_unique')) {
                $table->unique('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mobile_app_users', function (Blueprint $table) {
            if ($this->indexExists('mobile_app_users_username_unique')) {
                $table->dropUnique('mobile_app_users_username_unique');
            }

            if (! $this->indexExists('mobile_app_users_tenant_id_username_unique')) {
                $table->unique(['tenant_id', 'username']);
            }
        });
    }

    private function indexExists(string $indexName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return collect($connection->select('PRAGMA index_list(mobile_app_users)'))
                ->contains(fn ($index) => ($index->name ?? null) === $indexName);
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', $connection->getDatabaseName())
            ->where('table_name', 'mobile_app_users')
            ->where('index_name', $indexName)
            ->exists();
    }
};
