<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->string('category')->nullable()->after('view');
            $table->string('url', 2048)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropColumn(['category', 'url']);
        });
    }
};
