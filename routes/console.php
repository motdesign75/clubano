<?php

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoVereinSeeder;
use App\Services\TenantDemoDataGenerator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('clubano:seed-tenant-demo {tenantId=2}', function (int $tenantId, TenantDemoDataGenerator $generator) {
    $this->components->info("Erzeuge Testdaten nur für Tenant {$tenantId} ...");

    $summary = $generator->run($tenantId);

    $this->newLine();
    $this->components->info("Testdaten für '{$summary['tenant']}' wurden aktualisiert.");
    $this->table(
        ['Bereich', 'Anzahl'],
        collect($summary)
            ->except('tenant')
            ->map(fn ($value, $key) => [str_replace('_', ' ', (string) $key), $value])
            ->values()
            ->all()
    );

    $this->newLine();
    $this->components->warn('Es wurden nur Datensätze mit tenant_id = ' . $tenantId . ' angelegt oder ergänzt.');
})->purpose('Erzeugt tenant-sichere Demo- und Testdaten für einen einzelnen Verein');

Artisan::command('clubano:make-superadmin {email} {--name=Clubano Admin} {--password=}', function (string $email) {
    $password = (string) ($this->option('password') ?: Str::random(24));

    $user = User::withoutGlobalScopes()->updateOrCreate(
        ['email' => mb_strtolower($email)],
        [
            'name' => (string) $this->option('name'),
            'tenant_id' => null,
            'role' => User::ROLE_SUPERADMIN,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]
    );

    $this->components->info('Betreiberkonto ist bereit.');
    $this->line('E-Mail: ' . $user->email);
    $this->line('Rolle: ' . $user->role);
    $this->line('Tenant: ' . ($user->tenant_id ?? 'kein Verein'));

    if (! $this->option('password')) {
        $this->newLine();
        $this->components->warn('Ein neues Passwort wurde erzeugt. Bitte jetzt sichern:');
        $this->line($password);
    }
})->purpose('Erzeugt oder aktualisiert ein Clubano-Betreiberkonto ohne Vereinszuordnung');

Artisan::command('clubano:demo-reset', function () {
    $this->components->info('Setze den öffentlichen Clubano-Demozugang zurück ...');

    $this->call('db:seed', [
        '--class' => DemoVereinSeeder::class,
        '--force' => true,
    ]);

    $this->newLine();
    $this->components->info('Demo ist bereit.');
    $this->line('E-Mail: ' . DemoVereinSeeder::USER_EMAIL);
    $this->line('Passwort: ' . DemoVereinSeeder::USER_PASSWORD);
})->purpose('Setzt den öffentlichen Demo-Verein mit geschützten Beispieldaten zurück');

Artisan::command('clubano:recalculate-account-balances {tenantId?}', function (?int $tenantId = null) {
    $tenants = Tenant::withoutGlobalScopes()
        ->when($tenantId, fn ($query) => $query->whereKey($tenantId))
        ->orderBy('id')
        ->get(['id', 'name']);

    if ($tenants->isEmpty()) {
        $this->components->error('Kein passender Verein gefunden.');

        return 1;
    }

    $totalAccounts = 0;

    foreach ($tenants as $tenant) {
        $accounts = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('number')
            ->get();

        $accounts->each(fn (Account $account) => $account->updateBalance());
        $totalAccounts += $accounts->count();

        $this->components->info("{$tenant->name}: {$accounts->count()} Konten neu berechnet.");
    }

    $this->newLine();
    $this->components->info("Fertig. {$totalAccounts} Konten wurden neu berechnet.");

    return 0;
})->purpose('Berechnet die aktuellen Salden aller Konten aus abgeschlossenen Buchungen neu');
