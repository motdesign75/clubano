<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('email', 190)->nullable()->index();
            $table->string('phone_mobile', 50)->nullable();
            $table->string('phone_landline', 50)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('street_addition', 150)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Deutschland');
            $table->string('salutation', 20)->nullable(); // Frau, Herr, Divers …
            $table->string('title', 50)->nullable();
            $table->string('website', 200)->nullable();
            $table->string('source', 100)->nullable(); // z. B. Messe, Empfehlung
            $table->string('status', 40)->default('aktiv'); // aktiv, inaktiv
            $table->boolean('gdpr_consent')->default(false);
            $table->date('gdpr_consent_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable(); // Start: JSON; später Pivot-Relation
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'last_name']);
            $table->index(['tenant_id', 'company']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('contacts');
    }
};

