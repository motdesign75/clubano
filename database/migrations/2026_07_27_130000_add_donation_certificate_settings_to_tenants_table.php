<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('donation_certificates_enabled')->default(false)->after('is_demo');
            $table->boolean('donation_certificates_send_enabled')->default(false)->after('donation_certificates_enabled');
            $table->string('donation_tax_office')->nullable()->after('donation_certificates_send_enabled');
            $table->string('donation_tax_number')->nullable()->after('donation_tax_office');
            $table->string('donation_notice_authority')->nullable()->after('donation_tax_number');
            $table->date('donation_notice_date')->nullable()->after('donation_notice_authority');
            $table->date('donation_notice_valid_until')->nullable()->after('donation_notice_date');
            $table->text('donation_purposes')->nullable()->after('donation_notice_valid_until');
            $table->text('donation_email_body')->nullable()->after('donation_purposes');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'donation_certificates_enabled',
                'donation_certificates_send_enabled',
                'donation_tax_office',
                'donation_tax_number',
                'donation_notice_authority',
                'donation_notice_date',
                'donation_notice_valid_until',
                'donation_purposes',
                'donation_email_body',
            ]);
        });
    }
};
