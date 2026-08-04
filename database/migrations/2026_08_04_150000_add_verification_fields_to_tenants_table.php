<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('verification_status')->default('pending')->after('is_demo');
            $table->text('verification_notes')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verification_notes');
            $table->foreignId('verified_by_user_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->string('registration_contact_name')->nullable()->after('verified_by_user_id');
            $table->string('registration_role')->nullable()->after('registration_contact_name');
            $table->string('registration_website')->nullable()->after('registration_role');
            $table->text('registration_intent')->nullable()->after('registration_website');
            $table->string('registration_ip', 45)->nullable()->after('registration_intent');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn([
                'verification_status',
                'verification_notes',
                'verified_at',
                'registration_contact_name',
                'registration_role',
                'registration_website',
                'registration_intent',
                'registration_ip',
            ]);
        });
    }
};
