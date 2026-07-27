<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropUnique('donations_tenant_id_certificate_number_unique');
            $table->index(['tenant_id', 'certificate_number']);
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropIndex('donations_tenant_id_certificate_number_index');
            $table->unique(['tenant_id', 'certificate_number']);
        });
    }
};
