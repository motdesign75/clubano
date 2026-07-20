<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id'); // Mandant
            $table->foreignId('account_id')->constrained()->onDelete('cascade'); // Konto, auf das gebucht wird
            $table->date('date'); // Buchungsdatum
            $table->string('description'); // Beschreibung der Buchung
            $table->decimal('amount', 10, 2); // Betrag (negativ = Ausgabe, positiv = Einnahme)
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
