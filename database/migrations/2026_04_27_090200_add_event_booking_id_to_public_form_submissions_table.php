<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_form_submissions', function (Blueprint $table) {
            $table->foreignId('event_booking_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_form_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_booking_id');
        });
    }
};
