<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'member_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Flexible Rechnungsempfaenger duerfen bewusst ohne Mitglied existieren.
    }
};
