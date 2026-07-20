<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount', 5, 2)->nullable()->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount', 'tax_rate']);
        });
    }
};
