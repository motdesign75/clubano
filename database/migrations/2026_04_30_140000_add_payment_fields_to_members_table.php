<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('membership_interval');
            }

            if (!Schema::hasColumn('members', 'iban')) {
                $table->string('iban', 34)->nullable()->after('payment_method');
            }

            if (!Schema::hasColumn('members', 'bic')) {
                $table->string('bic', 11)->nullable()->after('iban');
            }

            if (!Schema::hasColumn('members', 'sepa_mandate_reference')) {
                $table->string('sepa_mandate_reference')->nullable()->after('bic');
            }

            if (!Schema::hasColumn('members', 'sepa_signed_at')) {
                $table->date('sepa_signed_at')->nullable()->after('sepa_mandate_reference');
            }

            if (!Schema::hasColumn('members', 'sepa_account_holder')) {
                $table->string('sepa_account_holder')->nullable()->after('sepa_signed_at');
            }

            if (!Schema::hasColumn('members', 'sepa_account_holder_street')) {
                $table->string('sepa_account_holder_street')->nullable()->after('sepa_account_holder');
            }

            if (!Schema::hasColumn('members', 'sepa_account_holder_zip')) {
                $table->string('sepa_account_holder_zip', 20)->nullable()->after('sepa_account_holder_street');
            }

            if (!Schema::hasColumn('members', 'sepa_account_holder_city')) {
                $table->string('sepa_account_holder_city')->nullable()->after('sepa_account_holder_zip');
            }

            if (!Schema::hasColumn('members', 'sepa_account_holder_country')) {
                $table->string('sepa_account_holder_country', 10)->nullable()->after('sepa_account_holder_city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $columns = [
                'payment_method',
                'iban',
                'bic',
                'sepa_mandate_reference',
                'sepa_signed_at',
                'sepa_account_holder',
                'sepa_account_holder_street',
                'sepa_account_holder_zip',
                'sepa_account_holder_city',
                'sepa_account_holder_country',
            ];

            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('members', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
