<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'whatsapp_phone')) {
                $table->string('whatsapp_phone')->nullable()->after('mobile');
            }

            if (!Schema::hasColumn('members', 'preferred_contact_channel')) {
                $table->string('preferred_contact_channel')->nullable()->after('landline');
            }

            if (!Schema::hasColumn('members', 'consent_email')) {
                $table->boolean('consent_email')->default(false)->after('preferred_contact_channel');
            }

            if (!Schema::hasColumn('members', 'consent_phone')) {
                $table->boolean('consent_phone')->default(false)->after('consent_email');
            }

            if (!Schema::hasColumn('members', 'consent_post')) {
                $table->boolean('consent_post')->default(false)->after('consent_phone');
            }

            if (!Schema::hasColumn('members', 'consent_whatsapp')) {
                $table->boolean('consent_whatsapp')->default(false)->after('consent_post');
            }

            if (!Schema::hasColumn('members', 'consent_given_at')) {
                $table->timestamp('consent_given_at')->nullable()->after('consent_whatsapp');
            }

            if (!Schema::hasColumn('members', 'last_contacted_at')) {
                $table->timestamp('last_contacted_at')->nullable()->after('consent_given_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $columns = [
                'whatsapp_phone',
                'preferred_contact_channel',
                'consent_email',
                'consent_phone',
                'consent_post',
                'consent_whatsapp',
                'consent_given_at',
                'last_contacted_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
