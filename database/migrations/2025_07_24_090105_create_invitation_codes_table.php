<?php

// database/migrations/xxxx_xx_xx_create_invitation_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invitation_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('invitation_codes');
    }
};
