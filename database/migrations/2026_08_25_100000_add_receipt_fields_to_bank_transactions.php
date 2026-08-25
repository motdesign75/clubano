<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('receipt_file')->nullable()->after('raw_data');
            $table->string('receipt_kind', 30)->nullable()->after('receipt_file');
            $table->json('receipt_meta')->nullable()->after('receipt_kind');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn(['receipt_file', 'receipt_kind', 'receipt_meta']);
        });
    }
};
