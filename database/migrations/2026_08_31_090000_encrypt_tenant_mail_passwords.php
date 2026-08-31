<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->whereNotNull('mail_password')
            ->orderBy('id')
            ->select(['id', 'mail_password'])
            ->chunkById(100, function ($tenants) {
                foreach ($tenants as $tenant) {
                    if (blank($tenant->mail_password)) {
                        continue;
                    }

                    try {
                        Crypt::decryptString($tenant->mail_password);
                        continue;
                    } catch (Throwable) {
                        DB::table('tenants')
                            ->where('id', $tenant->id)
                            ->update(['mail_password' => Crypt::encryptString($tenant->mail_password)]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('tenants')
            ->whereNotNull('mail_password')
            ->orderBy('id')
            ->select(['id', 'mail_password'])
            ->chunkById(100, function ($tenants) {
                foreach ($tenants as $tenant) {
                    if (blank($tenant->mail_password)) {
                        continue;
                    }

                    try {
                        $password = Crypt::decryptString($tenant->mail_password);
                    } catch (Throwable) {
                        continue;
                    }

                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update(['mail_password' => $password]);
                }
            });
    }
};
