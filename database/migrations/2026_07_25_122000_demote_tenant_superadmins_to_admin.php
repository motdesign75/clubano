<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('tenant_id')
            ->where('role', 'SAdmin')
            ->update(['role' => 'Admin']);
    }

    public function down(): void
    {
        // Intentionally empty: demoting tenant-bound superadmins is a security correction.
    }
};
