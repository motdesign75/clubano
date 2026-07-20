<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceNumberRangesTable extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_ranges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id'); // Mandantenschutz
            $table->string('type'); // z. B. beitrag, spende, rechnung
            $table->string('prefix')->nullable(); // z. B. BEITRAG-
            $table->string('suffix')->nullable(); // z. B. -2025
            $table->integer('start_number')->default(1);
            $table->integer('current_number')->default(0);
            $table->boolean('reset_yearly')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'type']); // Jeder Typ pro Tenant nur einmal
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_ranges');
    }
}
