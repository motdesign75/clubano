<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->longText('recognition_text')->nullable()->after('recognition_notes');
            $table->string('recognition_quality', 30)->nullable()->after('recognition_text');
            $table->json('recognition_fields')->nullable()->after('recognition_quality');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'recognition_text',
                'recognition_quality',
                'recognition_fields',
            ]);
        });
    }
};
