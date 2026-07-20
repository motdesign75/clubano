<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('email');             // Dateiname/Bildpfad
            $table->string('address')->nullable()->after('logo');
            $table->string('zip', 10)->nullable()->after('address');
            $table->string('city')->nullable()->after('zip');
            $table->string('phone')->nullable()->after('city');
            $table->string('register_number')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'logo',
                'address',
                'zip',
                'city',
                'phone',
                'register_number',
            ]);
        });
    }
};
