<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'member_id')) {
                $table->foreignId('member_id')->nullable()->after('tenant_id')->constrained('members')->nullOnDelete();
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'mobile_shifts_enabled')) {
                $table->boolean('mobile_shifts_enabled')->default(false)->after('reminders_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'mobile_shifts_enabled')) {
                $table->dropColumn('mobile_shifts_enabled');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'member_id')) {
                $table->dropConstrainedForeignId('member_id');
            }
        });
    }
};
