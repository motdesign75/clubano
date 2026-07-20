<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_bookings', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('public_form_submission_id')->constrained()->nullOnDelete();
            $table->index(['invoice_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('event_bookings', function (Blueprint $table) {
            $table->dropIndex(['invoice_id', 'payment_status']);
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
