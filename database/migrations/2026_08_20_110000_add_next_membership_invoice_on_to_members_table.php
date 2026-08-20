<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'next_membership_invoice_on')) {
                $table->date('next_membership_invoice_on')->nullable()->after('membership_interval');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'next_membership_invoice_on')) {
                $table->dropColumn('next_membership_invoice_on');
            }
        });
    }
};
