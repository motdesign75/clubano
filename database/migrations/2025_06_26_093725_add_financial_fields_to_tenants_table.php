<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'iban')) {
                $table->string('iban')->nullable()->after('register_number');
            }
            if (!Schema::hasColumn('tenants', 'bic')) {
                $table->string('bic')->nullable()->after('iban');
            }
            if (!Schema::hasColumn('tenants', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('bic');
            }
            if (!Schema::hasColumn('tenants', 'chairman')) {
                $table->string('chairman')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('tenants', 'letterhead')) {
                $table->string('letterhead')->nullable()->after('chairman');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['iban', 'bic', 'bank_name', 'chairman', 'letterhead']);
        });
    }
};
