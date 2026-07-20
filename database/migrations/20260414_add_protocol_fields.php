<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table) {

            if (!Schema::hasColumn('protocols', 'resolutions')) {
                $table->text('resolutions')->nullable()->after('content');
            }

            if (!Schema::hasColumn('protocols', 'next_meeting')) {
                $table->text('next_meeting')->nullable()->after('resolutions');
            }

            if (!Schema::hasColumn('protocols', 'attachments')) {
                $table->json('attachments')->nullable()->after('next_meeting');
            }

        });
    }

    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table) {

            $columnsToDrop = [];

            if (Schema::hasColumn('protocols', 'attachments')) {
                $columnsToDrop[] = 'attachments';
            }

            if (Schema::hasColumn('protocols', 'next_meeting')) {
                $columnsToDrop[] = 'next_meeting';
            }

            if (Schema::hasColumn('protocols', 'resolutions')) {
                $columnsToDrop[] = 'resolutions';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

        });
    }
};