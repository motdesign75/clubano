<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'membership_amount')) {
                $table->decimal('membership_amount', 8, 2)->nullable();
            }

            if (!Schema::hasColumn('members', 'membership_interval')) {
                $table->string('membership_interval')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['membership_amount', 'membership_interval']);
        });
    }
};
