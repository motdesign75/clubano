<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'family_payer_id')) {
                $table->foreignId('family_payer_id')
                    ->nullable()
                    ->after('next_membership_invoice_on')
                    ->constrained('members')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'family_payer_id')) {
                $table->dropConstrainedForeignId('family_payer_id');
            }
        });
    }
};
