<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operator_announcement_deliveries', function (Blueprint $table) {
            $table->uuid('tracking_token')->nullable()->unique()->after('error');
            $table->unsignedInteger('open_count')->default(0)->after('tracking_token');
            $table->timestamp('first_opened_at')->nullable()->after('open_count');
            $table->timestamp('last_opened_at')->nullable()->after('first_opened_at');
            $table->unsignedInteger('click_count')->default(0)->after('last_opened_at');
            $table->timestamp('first_clicked_at')->nullable()->after('click_count');
            $table->timestamp('last_clicked_at')->nullable()->after('first_clicked_at');
            $table->string('last_clicked_url', 2048)->nullable()->after('last_clicked_at');
        });

        DB::table('operator_announcement_deliveries')
            ->whereNull('tracking_token')
            ->orderBy('id')
            ->select('id')
            ->each(function ($delivery) {
                DB::table('operator_announcement_deliveries')
                    ->where('id', $delivery->id)
                    ->update(['tracking_token' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('operator_announcement_deliveries', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropColumn([
                'tracking_token',
                'open_count',
                'first_opened_at',
                'last_opened_at',
                'click_count',
                'first_clicked_at',
                'last_clicked_at',
                'last_clicked_url',
            ]);
        });
    }
};
