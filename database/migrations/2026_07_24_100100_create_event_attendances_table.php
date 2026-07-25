<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->boolean('attended')->default(false);
            $table->decimal('hours', 6, 2)->default(0);
            $table->boolean('counts_toward_required_hours')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'member_id']);
            $table->index(['tenant_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendances');
    }
};
