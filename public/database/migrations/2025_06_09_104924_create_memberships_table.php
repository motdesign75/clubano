<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();

            // Zugehöriger Verein (Mandant)
            $table->unsignedBigInteger('tenant_id');

            // Name der Mitgliedschaft, z. B. Aktiv, Fördernd
            $table->string('name');

            // Beitrag in Euro
            $table->decimal('fee', 8, 2)->nullable();

            // Abrechnungsintervall
            $table->enum('billing_cycle', ['monatlich', 'quartalsweise', 'halbjährlich', 'jährlich'])->default('jährlich');

            $table->timestamps();

            // Fremdschlüssel mit ON DELETE CASCADE
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
