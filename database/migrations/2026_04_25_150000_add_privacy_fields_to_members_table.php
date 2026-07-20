<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'consent_data_processing')) {
                $table->boolean('consent_data_processing')->default(false)->after('consent_whatsapp');
            }

            if (!Schema::hasColumn('members', 'consent_photo_internal')) {
                $table->boolean('consent_photo_internal')->default(false)->after('consent_data_processing');
            }

            if (!Schema::hasColumn('members', 'consent_photo_public')) {
                $table->boolean('consent_photo_public')->default(false)->after('consent_photo_internal');
            }

            if (!Schema::hasColumn('members', 'deletion_requested_at')) {
                $table->timestamp('deletion_requested_at')->nullable()->after('last_contacted_at');
            }

            if (!Schema::hasColumn('members', 'deletion_note')) {
                $table->text('deletion_note')->nullable()->after('deletion_requested_at');
            }

            if (!Schema::hasColumn('members', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('deletion_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $columns = [
                'consent_data_processing',
                'consent_photo_internal',
                'consent_photo_public',
                'deletion_requested_at',
                'deletion_note',
                'archived_at',
            ];

            $existing = array_filter($columns, fn ($column) => Schema::hasColumn('members', $column));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
