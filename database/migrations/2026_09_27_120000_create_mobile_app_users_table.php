<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('username');
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'username']);
            $table->unique(['tenant_id', 'member_id']);
        });

        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('abilities', 'like', '%mobile%')
            ->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_users');
    }
};
