<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('license_mode')->default('standard')->after('trial_ends_at');
            $table->timestamp('license_expires_at')->nullable()->after('license_mode');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'license_mode',
                'license_expires_at',
            ]);
        });
    }
};
