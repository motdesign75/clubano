<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('role')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('required_people')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('event_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_shift_id')->constrained('event_shifts')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('helper_name')->nullable();
            $table->string('helper_email')->nullable();
            $table->string('helper_phone')->nullable();
            $table->string('status')->default('confirmed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_shift_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_shift_assignments');
        Schema::dropIfExists('event_shifts');
    }
};
