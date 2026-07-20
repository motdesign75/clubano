<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Nur die SKR42-Konten für den Finanzbereich laden
        $this->call([
            AccountSeeder::class,
        ]);

        // ⚠️ Demo-Verein NUR manuell per php artisan db:seed --class=DemoVereinSeeder starten!
    }
}
