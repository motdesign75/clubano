<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('status', 30)->default('entwurf')->after('receipt_file');
            $table->timestamp('finalized_at')->nullable()->after('status');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        DB::table('transactions')
            ->update([
                'status' => 'entwurf',
                'finalized_at' => null,
                'finalized_by' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn(['status', 'finalized_at']);
        });
    }
};
