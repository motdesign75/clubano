<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('invited');
            $table->text('note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'member_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invitations');
    }
};
