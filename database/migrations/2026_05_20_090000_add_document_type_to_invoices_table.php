<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'document_type')) {
                $table->string('document_type', 30)->default('invoice')->after('tenant_id');
            }
        });

        DB::table('invoices')
            ->whereNull('document_type')
            ->update(['document_type' => 'invoice']);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'document_type')) {
                $table->dropColumn('document_type');
            }
        });
    }
};
