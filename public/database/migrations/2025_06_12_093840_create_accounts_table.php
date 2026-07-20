<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id'); // Multi-Tenant Support (Mandant)
            $table->string('name'); // Kontobezeichnung (z. B. Kasse)
            $table->string('number')->nullable(); // Kontonummer (z. B. SKR42: 1000)
            
            // Kontotypen nach SKR: aktiv, passiv, ertrag, aufwand
            $table->enum('type', ['bank', 'kasse', 'einnahme', 'ausgabe'])->default('kasse');

            $table->boolean('online')->default(false); // z. B. für Onlinebanking-Schnittstellen
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
