<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'mobile_app_sync_enabled')) {
                $table->boolean('mobile_app_sync_enabled')->default(false)->after('voucher_mail_body');
            }

            if (! Schema::hasColumn('tenants', 'mobile_app_sync_tag_id')) {
                $table->foreignId('mobile_app_sync_tag_id')
                    ->nullable()
                    ->after('mobile_app_sync_enabled')
                    ->constrained('tags')
                    ->nullOnDelete();
            }
        });

        Schema::table('mobile_app_users', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_app_users', 'sync_status')) {
                $table->string('sync_status', 32)->default('manual')->after('is_active');
            }

            if (! Schema::hasColumn('mobile_app_users', 'invitation_token')) {
                $table->string('invitation_token', 96)->nullable()->unique()->after('sync_status');
            }

            if (! Schema::hasColumn('mobile_app_users', 'invitation_sent_at')) {
                $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
            }

            if (! Schema::hasColumn('mobile_app_users', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('invitation_sent_at');
            }

            if (! Schema::hasColumn('mobile_app_users', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('accepted_at');
            }

            if (! Schema::hasColumn('mobile_app_users', 'sync_ignored_at')) {
                $table->timestamp('sync_ignored_at')->nullable()->after('synced_at');
            }

            if (! Schema::hasColumn('mobile_app_users', 'sync_error')) {
                $table->string('sync_error')->nullable()->after('sync_ignored_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mobile_app_users', function (Blueprint $table) {
            $columns = [
                'sync_status',
                'invitation_token',
                'invitation_sent_at',
                'accepted_at',
                'synced_at',
                'sync_ignored_at',
                'sync_error',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('mobile_app_users', $column)) {
                    if ($column === 'invitation_token') {
                        $table->dropUnique(['invitation_token']);
                    }

                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'mobile_app_sync_tag_id')) {
                $table->dropConstrainedForeignId('mobile_app_sync_tag_id');
            }

            if (Schema::hasColumn('tenants', 'mobile_app_sync_enabled')) {
                $table->dropColumn('mobile_app_sync_enabled');
            }
        });
    }
};
