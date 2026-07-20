<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Member;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoVereinSeeder extends Seeder
{
    public function run(): void
    {
        // Sicherheitsprüfung: nicht doppelt ausführen
        if (Tenant::where('slug', 'demo-verein')->exists()) {
            echo "⛔ Demo-Verein existiert bereits. Seeder wird übersprungen.\n";
            return;
        }

        // Demo-Tenant mit Pflichtfeldern
        $tenant = Tenant::create([
            'name'        => 'Demo Verein',
            'slug'        => 'demo-verein',
            'invite_code' => Str::uuid(),
            'email'       => 'verein@demo.de',
        ]);

        // Demo-Benutzer
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Demo Admin',
            'email'     => 'demo@demo.de',
            'password'  => Hash::make('demo123!'),
        ]);

        // 20 Demo-Mitglieder
        for ($i = 1; $i <= 20; $i++) {
            Member::create([
                'tenant_id'  => $tenant->id,
                'gender'     => 'männlich',
                'salutation' => 'Herr',
                'first_name' => "Max{$i}",
                'last_name'  => "Mustermann{$i}",
                'email'      => "mitglied{$i}@demo.de",
            ]);
        }

        echo "✅ Demo-Verein, Benutzer und Mitglieder erfolgreich erstellt.\n";
    }
}
