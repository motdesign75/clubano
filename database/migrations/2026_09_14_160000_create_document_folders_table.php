<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'parent_id', 'slug'], 'document_folders_unique_path');
            $table->index(['tenant_id', 'parent_id', 'sort_order'], 'document_folders_tree_idx');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('folder_id')
                ->nullable()
                ->after('status')
                ->constrained('document_folders')
                ->nullOnDelete();

            $table->index(['tenant_id', 'folder_id', 'archived_at'], 'documents_tenant_folder_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_tenant_folder_idx');
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('document_folders');
    }
};
