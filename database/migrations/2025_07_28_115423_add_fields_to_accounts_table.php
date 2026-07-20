<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'tenant_id')) {
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade')->after('id');
            }
            if (!Schema::hasColumn('accounts', 'name')) {
                $table->string('name')->after('tenant_id');
            }
            if (!Schema::hasColumn('accounts', 'type')) {
                $table->enum('type', ['bank', 'kasse'])->default('kasse')->after('name');
            }
            if (!Schema::hasColumn('accounts', 'iban')) {
                $table->string('iban')->nullable()->after('type');
            }
            if (!Schema::hasColumn('accounts', 'bic')) {
                $table->string('bic')->nullable()->after('iban');
            }
            if (!Schema::hasColumn('accounts', 'description')) {
                $table->text('description')->nullable()->after('bic');
            }
            if (!Schema::hasColumn('accounts', 'active')) {
                $table->boolean('active')->default(true)->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_id',
                'name',
                'type',
                'iban',
                'bic',
                'description',
                'active',
            ]);
        });
    }
};
