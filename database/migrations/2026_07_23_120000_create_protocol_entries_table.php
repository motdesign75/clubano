<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('protocol_entries')) {
            return;
        }

        Schema::create('protocol_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('protocol_id')->constrained('protocols')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('responsible_name')->nullable();
            $table->date('due_date')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->boolean('visible_in_protocol')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'protocol_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_entries');
    }
};
