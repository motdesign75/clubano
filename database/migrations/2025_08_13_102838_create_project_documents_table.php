<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Mandant & Projektbezug
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();

            // Uploader (optional)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Datei-Metadaten
            $table->string('disk')->default('public');
            $table->string('path');                 // z.B. projects/{projectId}/filename.ext
            $table->string('original_name');        // Original-Dateiname
            $table->unsignedBigInteger('size');     // Bytes
            $table->string('mime_type')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('project_documents');
    }
};
