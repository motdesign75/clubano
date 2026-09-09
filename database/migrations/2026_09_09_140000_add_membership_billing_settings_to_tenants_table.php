<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('membership_billing_reminders_enabled')->default(true)->after('letter_bottom_margin_mm');
            $table->boolean('dunning_enabled')->default(false)->after('membership_billing_reminders_enabled');
            $table->unsignedSmallInteger('dunning_first_after_days')->default(14)->after('dunning_enabled');
            $table->unsignedSmallInteger('dunning_second_after_days')->default(28)->after('dunning_first_after_days');
            $table->unsignedSmallInteger('dunning_final_after_days')->default(42)->after('dunning_second_after_days');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'membership_billing_reminders_enabled',
                'dunning_enabled',
                'dunning_first_after_days',
                'dunning_second_after_days',
                'dunning_final_after_days',
            ]);
        });
    }
};
