<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automated_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('occasion', 80);
            $table->boolean('enabled')->default(false);
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->unsignedTinyInteger('days_before')->default(0);
            $table->string('send_time', 5)->default('09:00');
            $table->timestamps();

            $table->unique(['tenant_id', 'occasion']);
            $table->index(['occasion', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automated_mail_settings');
    }
};
