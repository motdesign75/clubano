<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            $table->boolean('confirmation_mail_enabled')->default(false)->after('success_message');
            $table->string('confirmation_mail_subject')->nullable()->after('confirmation_mail_enabled');
            $table->longText('confirmation_mail_body')->nullable()->after('confirmation_mail_subject');
        });
    }

    public function down(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_mail_enabled',
                'confirmation_mail_subject',
                'confirmation_mail_body',
            ]);
        });
    }
};
