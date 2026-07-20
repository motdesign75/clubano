<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Mandant (Clubano verwendet BIGINT für tenants.id)
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Projekt-Referenz (projects.id = ULID/char(26))
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Planung/Ist-Zeiten
            $table->date('plan_start')->nullable();
            $table->date('plan_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();

            // Status & Fortschritt
            $table->string('status')->default('open'); // open|in_progress|blocked|done
            $table->unsignedTinyInteger('percent_done')->default(0); // 0..100

            // Verantwortlich (users.id = BIGINT)
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

            // Sonstiges
            $table->unsignedTinyInteger('priority')->default(3); // 1 hoch ... 5 niedrig
            $table->string('type')->default('task'); // task|milestone

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tasks');
    }
};
