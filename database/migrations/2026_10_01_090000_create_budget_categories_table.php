<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('name', 120);
            $table->string('color', 30)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'active', 'sort_order']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('budget_category_id')
                ->nullable()
                ->after('tax_area')
                ->constrained('budget_categories')
                ->nullOnDelete();
        });

        Schema::table('budget_plan_items', function (Blueprint $table) {
            $table->foreignId('budget_category_id')
                ->nullable()
                ->after('account_id')
                ->constrained('budget_categories')
                ->nullOnDelete();
        });

        $defaults = [
            ['Mitgliedschaft', 'blue'],
            ['Veranstaltungen', 'amber'],
            ['Gastronomie', 'emerald'],
            ['Sponsoring', 'purple'],
            ['Jugend', 'cyan'],
            ['Sportbetrieb', 'lime'],
            ['Verwaltung', 'slate'],
            ['Oeffentlichkeitsarbeit', 'rose'],
            ['Vereinsheim', 'indigo'],
            ['Sonstiges', 'zinc'],
        ];

        $now = now();
        DB::table('tenants')->select('id')->orderBy('id')->chunk(100, function ($tenants) use ($defaults, $now) {
            foreach ($tenants as $tenant) {
                foreach ($defaults as $index => [$name, $color]) {
                    DB::table('budget_categories')->insertOrIgnore([
                        'tenant_id' => $tenant->id,
                        'name' => $name,
                        'color' => $color,
                        'sort_order' => ($index + 1) * 10,
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::table('budget_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_category_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_category_id');
        });

        Schema::dropIfExists('budget_categories');
    }
};
