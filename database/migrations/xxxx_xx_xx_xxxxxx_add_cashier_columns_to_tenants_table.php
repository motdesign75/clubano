<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Cashier Standard (Customer)
            if (!Schema::hasColumn('tenants', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->index();
            }

            // Payment method metadata (optional, aber Cashier nutzt es)
            if (!Schema::hasColumn('tenants', 'pm_type')) {
                $table->string('pm_type')->nullable();
            }

            if (!Schema::hasColumn('tenants', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable();
            }

            // Trial (optional)
            if (!Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable();
            }

            // Optional: für Debug/Reports angenehm
            if (!Schema::hasColumn('tenants', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'stripe_id')) {
                $table->dropColumn('stripe_id');
            }
            if (Schema::hasColumn('tenants', 'pm_type')) {
                $table->dropColumn('pm_type');
            }
            if (Schema::hasColumn('tenants', 'pm_last_four')) {
                $table->dropColumn('pm_last_four');
            }
            if (Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }
            if (Schema::hasColumn('tenants', 'stripe_subscription_id')) {
                $table->dropColumn('stripe_subscription_id');
            }
        });
    }
};
