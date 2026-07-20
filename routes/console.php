<?php

use App\Services\TenantDemoDataGenerator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
