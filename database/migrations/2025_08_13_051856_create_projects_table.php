cat > database/migrations/2025_08_13_051856_create_projects_table.php <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Clubano nutzt BIGINT für tenants.id -> foreignId verwenden
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status')->default('active');

            // users.id ist BIGINT -> foreignId verwenden
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('projects');
    }
};