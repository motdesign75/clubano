<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->uuid('recurrence_group_id')->nullable()->after('updated_by')->index();
            $table->string('recurrence_frequency')->nullable()->after('recurrence_group_id');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_frequency');
            $table->date('recurrence_until')->nullable()->after('recurrence_interval');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'recurrence_group_id',
                'recurrence_frequency',
                'recurrence_interval',
                'recurrence_until',
            ]);
        });
    }
};
