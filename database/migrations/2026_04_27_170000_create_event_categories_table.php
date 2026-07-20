<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->default('#2563EB');
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('tenant_id')->constrained('event_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('event_categories');
    }
};
