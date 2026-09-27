<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->uuid('mobile_identity_uuid')->nullable()->unique()->after('photo');
            $table->string('mobile_identity_secret', 96)->nullable()->after('mobile_identity_uuid');
            $table->timestamp('mobile_identity_rotated_at')->nullable()->after('mobile_identity_secret');
            $table->timestamp('mobile_identity_revoked_at')->nullable()->after('mobile_identity_rotated_at');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['mobile_identity_uuid']);
            $table->dropColumn([
                'mobile_identity_uuid',
                'mobile_identity_secret',
                'mobile_identity_rotated_at',
                'mobile_identity_revoked_at',
            ]);
        });
    }
};
