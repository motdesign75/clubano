<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_form_submissions', function (Blueprint $table) {
            $table->string('status')->default('active')->after('phone');
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->softDeletes()->after('cancelled_at');
        });

        DB::table('public_form_submissions')
            ->whereNull('status')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('public_form_submissions', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['status', 'cancelled_at']);
        });
    }
};
