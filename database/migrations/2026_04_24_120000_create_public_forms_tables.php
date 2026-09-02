<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('form_type', ['general', 'contact', 'membership', 'event'])->default('general');
            $table->text('success_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'form_type']);
        });

        Schema::create('public_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_form_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('slug');
            $table->enum('field_type', ['text', 'email', 'iban', 'number', 'date', 'textarea', 'select', 'radio', 'checkbox_group', 'checkbox', 'heading', 'content', 'divider'])->default('text');
            $table->text('help_text')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['public_form_id', 'slug']);
        });

        Schema::create('public_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('answers');
            $table->timestamps();

            $table->index(['public_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_form_submissions');
        Schema::dropIfExists('public_form_fields');
        Schema::dropIfExists('public_forms');
    }
};
