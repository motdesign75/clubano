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
        // Keine Benutzer erstellen, da bereits echte Vereinsdaten existieren

        // Nur die SKR42-Konten für den Finanzbereich laden
        $this->call([
            AccountSeeder::class,
        ]);
    }
}
