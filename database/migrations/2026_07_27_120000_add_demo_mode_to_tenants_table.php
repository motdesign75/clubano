<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('license_expires_at');
                $table->index('is_demo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'is_demo')) {
                $table->dropIndex(['is_demo']);
                $table->dropColumn('is_demo');
            }
        });
    }
};
