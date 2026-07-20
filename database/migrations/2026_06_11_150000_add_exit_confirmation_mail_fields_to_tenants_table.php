<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'member_exit_mail_enabled')) {
                $table->boolean('member_exit_mail_enabled')->default(false)->after('mail_from_name');
            }

            if (! Schema::hasColumn('tenants', 'member_exit_mail_subject')) {
                $table->string('member_exit_mail_subject')->nullable()->after('member_exit_mail_enabled');
            }

            if (! Schema::hasColumn('tenants', 'member_exit_mail_body')) {
                $table->longText('member_exit_mail_body')->nullable()->after('member_exit_mail_subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $drops = [];

            foreach ([
                'member_exit_mail_enabled',
                'member_exit_mail_subject',
                'member_exit_mail_body',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
