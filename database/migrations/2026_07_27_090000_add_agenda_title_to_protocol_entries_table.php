<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('protocol_entries', 'agenda_title')) {
                $table->string('agenda_title')->nullable()->after('protocol_id');
                $table->index(['tenant_id', 'protocol_id', 'agenda_title'], 'protocol_entries_agenda_lookup');
            }
        });
    }

    public function down(): void
    {
        Schema::table('protocol_entries', function (Blueprint $table) {
            if (Schema::hasColumn('protocol_entries', 'agenda_title')) {
                $table->dropIndex('protocol_entries_agenda_lookup');
                $table->dropColumn('agenda_title');
            }
        });
    }
};
