<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'voucher_template_path')) {
                $table->string('voucher_template_path')->nullable()->after('donation_email_body');
            }

            if (! Schema::hasColumn('tenants', 'voucher_code_position')) {
                $table->string('voucher_code_position')->default('bottom-right')->after('voucher_template_path');
            }

            if (! Schema::hasColumn('tenants', 'voucher_code_color')) {
                $table->string('voucher_code_color')->default('#0f172a')->after('voucher_code_position');
            }

            if (! Schema::hasColumn('tenants', 'voucher_show_qr')) {
                $table->boolean('voucher_show_qr')->default(true)->after('voucher_code_color');
            }

            if (! Schema::hasColumn('tenants', 'voucher_mail_subject')) {
                $table->string('voucher_mail_subject')->nullable()->after('voucher_show_qr');
            }

            if (! Schema::hasColumn('tenants', 'voucher_mail_body')) {
                $table->text('voucher_mail_body')->nullable()->after('voucher_mail_subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach ([
                'voucher_mail_body',
                'voucher_mail_subject',
                'voucher_show_qr',
                'voucher_code_color',
                'voucher_code_position',
                'voucher_template_path',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
