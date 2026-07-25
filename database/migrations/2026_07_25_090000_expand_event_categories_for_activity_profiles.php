<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->string('icon', 40)->default('calendar')->after('color');
            $table->foreignId('default_target_tag_id')->nullable()->after('icon')->constrained('tags')->nullOnDelete();
            $table->string('default_visibility', 20)->default('public')->after('default_target_tag_id');
            $table->boolean('attendance_enabled_default')->default(false)->after('default_visibility');
            $table->boolean('response_required_default')->default(false)->after('attendance_enabled_default');
            $table->boolean('counts_toward_required_hours_default')->default(false)->after('response_required_default');
            $table->boolean('reminders_enabled_default')->default(false)->after('counts_toward_required_hours_default');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('target_tag_id')->nullable()->after('responsible_user_id')->constrained('tags')->nullOnDelete();
            $table->boolean('attendance_enabled')->default(false)->after('booking_enabled');
            $table->boolean('response_required')->default(false)->after('attendance_enabled');
            $table->boolean('counts_toward_required_hours')->default(false)->after('response_required');
            $table->boolean('reminders_enabled')->default(false)->after('counts_toward_required_hours');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_tag_id');
            $table->dropColumn([
                'attendance_enabled',
                'response_required',
                'counts_toward_required_hours',
                'reminders_enabled',
            ]);
        });

        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_target_tag_id');
            $table->dropColumn([
                'icon',
                'default_visibility',
                'attendance_enabled_default',
                'response_required_default',
                'counts_toward_required_hours_default',
                'reminders_enabled_default',
            ]);
        });
    }
};
