<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['number' => '1000', 'name' => 'Kasse', 'type' => 'kasse'],
            ['number' => '1200', 'name' => 'Bank', 'type' => 'bank'],
            ['number' => '8400', 'name' => 'Mitgliedsbeiträge', 'type' => 'einnahme'],
            ['number' => '1001', 'name' => 'Portokasse', 'type' => 'kasse'],
            ['number' => '6600', 'name' => 'Büromaterial', 'type' => 'ausgabe'],
        ];

        foreach ($accounts as $data) {
            Account::create([
                ...$data,
                'tenant_id' => 1, // Testdaten – später dynamisch setzen
            ]);
        }
    }
}
