<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('template_dispatch_logs', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->after('message_excerpt');
            $table->unsignedInteger('open_count')->default(0)->after('tracking_token');
            $table->timestamp('first_opened_at')->nullable()->after('open_count');
            $table->timestamp('last_opened_at')->nullable()->after('first_opened_at');
            $table->unsignedInteger('click_count')->default(0)->after('last_opened_at');
            $table->timestamp('first_clicked_at')->nullable()->after('click_count');
            $table->timestamp('last_clicked_at')->nullable()->after('first_clicked_at');

            $table->unique('tracking_token');
            $table->index(['tenant_id', 'last_opened_at']);
            $table->index(['tenant_id', 'last_clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('template_dispatch_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'last_opened_at']);
            $table->dropIndex(['tenant_id', 'last_clicked_at']);
            $table->dropUnique(['tracking_token']);
            $table->dropColumn([
                'tracking_token',
                'open_count',
                'first_opened_at',
                'last_opened_at',
                'click_count',
                'first_clicked_at',
                'last_clicked_at',
            ]);
        });
    }
};
