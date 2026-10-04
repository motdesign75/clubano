<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'e_invoice_required')) {
                $table->boolean('e_invoice_required')->default(false)->after('internal_notes');
            }

            if (! Schema::hasColumn('contacts', 'e_invoice_format')) {
                $table->string('e_invoice_format', 30)->default('xrechnung')->after('e_invoice_required');
            }

            if (! Schema::hasColumn('contacts', 'e_invoice_buyer_reference')) {
                $table->string('e_invoice_buyer_reference', 100)->nullable()->after('e_invoice_format');
            }

            if (! Schema::hasColumn('contacts', 'e_invoice_order_reference')) {
                $table->string('e_invoice_order_reference', 100)->nullable()->after('e_invoice_buyer_reference');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'e_invoice_enabled')) {
                $table->boolean('e_invoice_enabled')->default(false)->after('recipient_country');
            }

            if (! Schema::hasColumn('invoices', 'e_invoice_format')) {
                $table->string('e_invoice_format', 30)->default('xrechnung')->after('e_invoice_enabled');
            }

            if (! Schema::hasColumn('invoices', 'e_invoice_buyer_reference')) {
                $table->string('e_invoice_buyer_reference', 100)->nullable()->after('e_invoice_format');
            }

            if (! Schema::hasColumn('invoices', 'e_invoice_order_reference')) {
                $table->string('e_invoice_order_reference', 100)->nullable()->after('e_invoice_buyer_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach (['e_invoice_order_reference', 'e_invoice_buyer_reference', 'e_invoice_format', 'e_invoice_enabled'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('contacts', function (Blueprint $table) {
            foreach (['e_invoice_order_reference', 'e_invoice_buyer_reference', 'e_invoice_format', 'e_invoice_required'] as $column) {
                if (Schema::hasColumn('contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
