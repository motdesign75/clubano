<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('follow_up_at')->nullable()->after('plan_end');
            $table->string('related_type')->nullable()->after('type');
            $table->string('related_id')->nullable()->after('related_type');
            $table->foreignId('created_by')->nullable()->after('assignee_id')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'follow_up_at',
                'related_type',
                'related_id',
                'completed_at',
            ]);
        });
    }
};
