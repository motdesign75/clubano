<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('tenant_id');

            $table->unsignedBigInteger('invoice_id');

            $table->unsignedBigInteger('account_id')->nullable(); 
            // Bank / Kasse

            $table->decimal('amount', 10, 2);

            $table->date('payment_date');

            $table->string('note')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};