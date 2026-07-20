<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (!Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'period_year')) {
                $table->integer('period_year')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'period_from')) {
                $table->date('period_from')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'period_to')) {
                $table->date('period_to')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'total')) {
                $table->decimal('total', 10, 2)->default(0);
            }

            if (!Schema::hasColumn('invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }

        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            // optional — lassen wir leer, damit nichts gelöscht wird

        });
    }
};