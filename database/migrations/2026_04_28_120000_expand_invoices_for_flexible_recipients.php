<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('member_id');
            }
            if (!Schema::hasColumn('invoices', 'recipient_type')) {
                $table->string('recipient_type')->default('member')->after('contact_id');
            }
            if (!Schema::hasColumn('invoices', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('recipient_type');
            }
            if (!Schema::hasColumn('invoices', 'recipient_company')) {
                $table->string('recipient_company')->nullable()->after('recipient_name');
            }
            if (!Schema::hasColumn('invoices', 'recipient_salutation')) {
                $table->string('recipient_salutation')->nullable()->after('recipient_company');
            }
            if (!Schema::hasColumn('invoices', 'recipient_email')) {
                $table->string('recipient_email')->nullable()->after('recipient_salutation');
            }
            if (!Schema::hasColumn('invoices', 'recipient_street')) {
                $table->string('recipient_street')->nullable()->after('recipient_email');
            }
            if (!Schema::hasColumn('invoices', 'recipient_zip')) {
                $table->string('recipient_zip')->nullable()->after('recipient_street');
            }
            if (!Schema::hasColumn('invoices', 'recipient_city')) {
                $table->string('recipient_city')->nullable()->after('recipient_zip');
            }
            if (!Schema::hasColumn('invoices', 'recipient_country')) {
                $table->string('recipient_country')->nullable()->after('recipient_city');
            }
            if (!Schema::hasColumn('invoices', 'intro_text')) {
                $table->text('intro_text')->nullable()->after('recipient_country');
            }
            if (!Schema::hasColumn('invoices', 'payment_text')) {
                $table->text('payment_text')->nullable()->after('intro_text');
            }
            if (!Schema::hasColumn('invoices', 'closing_text')) {
                $table->text('closing_text')->nullable()->after('payment_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $columns = [
                'contact_id',
                'recipient_type',
                'recipient_name',
                'recipient_company',
                'recipient_salutation',
                'recipient_email',
                'recipient_street',
                'recipient_zip',
                'recipient_city',
                'recipient_country',
                'intro_text',
                'payment_text',
                'closing_text',
            ];

            $existing = array_filter($columns, fn (string $column) => Schema::hasColumn('invoices', $column));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
