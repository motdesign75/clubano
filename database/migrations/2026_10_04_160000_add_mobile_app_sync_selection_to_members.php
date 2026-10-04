<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'mobile_app_sync_enabled')) {
                $table->boolean('mobile_app_sync_enabled')->default(false)->after('mobile_identity_revoked_at');
            }
        });

        DB::table('members')
            ->join('mobile_app_users', 'mobile_app_users.member_id', '=', 'members.id')
            ->where('mobile_app_users.is_active', true)
            ->update(['members.mobile_app_sync_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'mobile_app_sync_enabled')) {
                $table->dropColumn('mobile_app_sync_enabled');
            }
        });
    }
};
