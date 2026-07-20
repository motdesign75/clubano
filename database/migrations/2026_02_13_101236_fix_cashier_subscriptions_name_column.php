<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Cashier erwartet "name" statt "type"
            if (!Schema::hasColumn('subscriptions', 'name') && Schema::hasColumn('subscriptions', 'type')) {
                $table->renameColumn('type', 'name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'name') && !Schema::hasColumn('subscriptions', 'type')) {
                $table->renameColumn('name', 'type');
            }
        });
    }
};
