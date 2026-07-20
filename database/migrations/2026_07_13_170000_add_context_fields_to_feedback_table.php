<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            if (!Schema::hasColumn('feedback', 'category')) {
                $table->string('category', 50)->default('Allgemein')->after('user_id');
            }

            if (!Schema::hasColumn('feedback', 'url')) {
                $table->string('url', 2048)->nullable()->after('view');
            }

            if (!Schema::hasColumn('feedback', 'page_title')) {
                $table->string('page_title')->nullable()->after('url');
            }

            if (!Schema::hasColumn('feedback', 'device_label')) {
                $table->string('device_label', 120)->nullable()->after('page_title');
            }

            if (!Schema::hasColumn('feedback', 'viewport')) {
                $table->string('viewport', 50)->nullable()->after('device_label');
            }

            if (!Schema::hasColumn('feedback', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('viewport');
            }

            if (!Schema::hasColumn('feedback', 'screenshot_path')) {
                $table->string('screenshot_path')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $columns = [
                'category',
                'url',
                'page_title',
                'device_label',
                'viewport',
                'user_agent',
                'screenshot_path',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('feedback', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
