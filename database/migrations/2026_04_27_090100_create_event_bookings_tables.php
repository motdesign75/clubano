<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_form_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('booking_reference')->unique();
            $table->string('booker_name');
            $table->string('booker_email')->nullable();
            $table->string('booker_phone')->nullable();
            $table->unsignedInteger('participant_count')->default(1);
            $table->decimal('price_per_person', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->enum('payment_status', ['not_required', 'open', 'paid', 'cancelled'])->default('not_required');
            $table->enum('booking_status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
            $table->index(['event_id', 'payment_status']);
        });

        Schema::create('event_booking_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('answers')->nullable();
            $table->timestamps();

            $table->index(['event_booking_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_booking_participants');
        Schema::dropIfExists('event_bookings');
    }
};
