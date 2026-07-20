<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sepa_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sequence_type', 10);
            $table->date('collection_date');
            $table->unsignedInteger('transaction_count');
            $table->decimal('control_sum', 12, 2);
            $table->string('file_name')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sepa_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sepa_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number');
            $table->string('member_name')->nullable();
            $table->string('mandate_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sepa_run_items');
        Schema::dropIfExists('sepa_runs');
    }
};
