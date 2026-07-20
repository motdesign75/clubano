<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('title');
            $table->string('status')->default('entwurf');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'year']);
        });

        Schema::create('budget_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->decimal('planned_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_items');
        Schema::dropIfExists('budget_plans');
    }
};
