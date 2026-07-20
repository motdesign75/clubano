<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            if (!Schema::hasColumn('protocols', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('attachments');
            }

            if (!Schema::hasColumn('protocols', 'deleted_at')) {
                $table->softDeletes()->after('archived_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            if (Schema::hasColumn('protocols', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('protocols', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
