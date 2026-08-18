<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'buyer_email')) {
                $table->string('buyer_email')->nullable()->after('buyer_name');
            }

            if (! Schema::hasColumn('vouchers', 'recipient_email')) {
                $table->string('recipient_email')->nullable()->after('recipient_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'recipient_email')) {
                $table->dropColumn('recipient_email');
            }

            if (Schema::hasColumn('vouchers', 'buyer_email')) {
                $table->dropColumn('buyer_email');
            }
        });
    }
};
