<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('exit_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'termination_date')) {
                $table->dropColumn('termination_date');
            }
        });
    }
};
