<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->string('status')->default('entwurf'); // entwurf, versendet, bezahlt, storniert
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
