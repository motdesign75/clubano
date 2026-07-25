<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('required_service_hours', 6, 2)->default(0)->after('membership_interval');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('required_service_hours');
        });
    }
};
