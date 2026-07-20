<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Spalte nur hinzufügen, wenn sie noch nicht existiert
            if (!Schema::hasColumn('members', 'photo')) {
                $table->string('photo')->nullable()->after('birthday');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Fehlervermeidung beim Rollback
            if (Schema::hasColumn('members', 'photo')) {
                $table->dropColumn('photo');
            }
        });
    }
};
