<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->decimal('amount', 8, 2)->default(0)->after('name'); // z. B. 10.00 €
            $table->enum('interval', ['monatlich', 'vierteljährlich', 'halbjährlich', 'jährlich'])->default('jährlich')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['amount', 'interval']);
        });
    }
};
