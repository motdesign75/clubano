<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('waiting');
            $table->timestamp('joined_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'member_id']);
            $table->index(['tenant_id', 'event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_waitlist_entries');
    }
};
