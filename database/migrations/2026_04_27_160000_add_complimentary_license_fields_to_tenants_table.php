<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable();
            }

            if (!Schema::hasColumn('tenants', 'license_mode')) {
                $table->string('license_mode')->default('standard')->after('trial_ends_at');
            }

            if (!Schema::hasColumn('tenants', 'license_expires_at')) {
                $table->timestamp('license_expires_at')->nullable()->after('license_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'license_mode')) {
                $table->dropColumn('license_mode');
            }

            if (Schema::hasColumn('tenants', 'license_expires_at')) {
                $table->dropColumn('license_expires_at');
            }
        });
    }
};
