<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            // Block: Mitglied
            $table->enum('gender', ['weiblich', 'männlich', 'divers'])->nullable();
            $table->enum('salutation', ['Frau', 'Herr', 'Liebe', 'Lieber', 'Hallo'])->nullable();
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('organization')->nullable();
            $table->date('birthday')->nullable();
            $table->string('photo')->nullable(); // NEU: Profilfoto

            // Block: Mitgliedschaft
            $table->string('member_id')->nullable();
            $table->date('entry_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->foreignId('membership_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('membership_amount', 8, 2)->nullable(); // NEU: Betrag
            $table->string('membership_interval')->nullable();      // NEU: Abrechnungsintervall

            // Block: Kommunikation
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('landline')->nullable();

            // Block: Adresse
            $table->string('street')->nullable();
            $table->string('address_addition')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Deutschland');
            $table->string('care_of')->nullable();

            $table->timestamps();

            // Fremdschlüssel
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
