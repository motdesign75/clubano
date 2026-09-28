<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_push_tokens', function (Blueprint $table) {
            $table->string('provider', 20)->default('expo')->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_push_tokens', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
