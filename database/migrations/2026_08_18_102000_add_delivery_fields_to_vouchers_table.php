<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'delivery_method')) {
                $table->string('delivery_method', 40)->default('pickup')->after('recipient_email');
            }

            if (! Schema::hasColumn('vouchers', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('delivery_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('vouchers', 'delivered_at') ? 'delivered_at' : null,
                Schema::hasColumn('vouchers', 'delivery_method') ? 'delivery_method' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
