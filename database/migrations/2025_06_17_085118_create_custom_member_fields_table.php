<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_member_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name'); // z. B. "Trikotgröße"
            $table->string('slug'); // z. B. "trikotgroesse"
            $table->string('type'); // text, select, checkbox, etc.
            $table->json('options')->nullable(); // bei select/radio
            $table->boolean('required')->default(false);
            $table->integer('order')->default(0); // Reihenfolge im Formular
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_member_fields');
    }
};
