<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plan_items', function (Blueprint $table) {
            $table->decimal('period_amount', 12, 2)->default(0)->after('type');
            $table->string('planning_cycle')->default('yearly')->after('period_amount');
        });

        DB::table('budget_plan_items')->update([
            'period_amount' => DB::raw('planned_amount'),
            'planning_cycle' => 'yearly',
        ]);
    }

    public function down(): void
    {
        Schema::table('budget_plan_items', function (Blueprint $table) {
            $table->dropColumn(['period_amount', 'planning_cycle']);
        });
    }
};
