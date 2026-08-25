<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operator_announcements', function (Blueprint $table) {
            $table->string('category')->default('product_update')->after('cta_url');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('operator_updates_unsubscribed_at')->nullable()->after('update_notice_dismissed_version');
        });
    }

    public function down(): void
    {
        Schema::table('operator_announcements', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('operator_updates_unsubscribed_at');
        });
    }
};
