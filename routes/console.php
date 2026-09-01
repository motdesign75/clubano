<?php

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Event;
use App\Models\OperatorAnnouncement;
use App\Models\Protocol;
use App\Models\PublicForm;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\ReceiptStorage;
use Database\Seeders\DemoVereinSeeder;
use App\Services\TenantDemoDataGenerator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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

Artisan::command('clubano:security-check {--json : Gibt das Ergebnis als JSON aus}', function () {
    $checks = [];

    $add = function (string $level, string $title, string $message, array $items = []) use (&$checks): void {
        $checks[] = compact('level', 'title', 'message', 'items');
    };

    $forbiddenPublicPaths = [
        '.env',
        'app',
        'artisan',
        'bootstrap',
        'composer.json',
        'composer.lock',
        'config',
        'database',
        'package.json',
        'package-lock.json',
        'resources',
        'routes',
        'tests',
        'vendor',
    ];

    $publicFindings = collect($forbiddenPublicPaths)
        ->filter(fn (string $path) => File::exists(public_path($path)))
        ->map(fn (string $path) => 'public/' . $path)
        ->values()
        ->merge(collect([
            ...File::glob(public_path('.env*')) ?: [],
            ...File::glob(public_path('*.sql')) ?: [],
            ...File::glob(public_path('*.zip')) ?: [],
            ...File::glob(public_path('*.tar')) ?: [],
            ...File::glob(public_path('*.tgz')) ?: [],
            ...File::glob(public_path('*.tar.gz')) ?: [],
            ...File::glob(public_path('*.bak')) ?: [],
            ...File::glob(public_path('*.old')) ?: [],
        ])->map(fn (string $path) => 'public/' . basename($path)))
        ->unique()
        ->values()
        ->all();

    if ($publicFindings !== []) {
        $add('critical', 'Webroot enthält interne Dateien', 'Im öffentlichen Ordner liegen Dateien oder Verzeichnisse, die dort nicht hingehören.', $publicFindings);
    } else {
        $add('ok', 'Webroot sauber', 'Keine typischen Laravel-Codekopien oder Konfigurationsdateien im öffentlichen Ordner gefunden.');
    }

    $rootArtifacts = collect([
        ...File::glob(base_path('*.sql')) ?: [],
        ...File::glob(base_path('*.zip')) ?: [],
        ...File::glob(base_path('*.tar')) ?: [],
        ...File::glob(base_path('*.tgz')) ?: [],
        ...File::glob(base_path('*.tar.gz')) ?: [],
    ])
        ->map(fn (string $path) => basename($path))
        ->unique()
        ->values()
        ->all();

    if ($rootArtifacts !== []) {
        $add('critical', 'Daten- oder Backup-Artefakte im Projektstamm', 'SQL-Dumps und Archivdateien dürfen nicht im Projektstamm liegen.', $rootArtifacts);
    } else {
        $add('ok', 'Keine Dumps im Projektstamm', 'Keine SQL-Dumps oder Archivdateien im Projektstamm gefunden.');
    }

    $storageLinked = File::exists(public_path('storage')) && is_link(public_path('storage'));
    $add($storageLinked ? 'ok' : 'warning', 'Storage-Link', $storageLinked ? 'Der öffentliche Storage-Link ist vorhanden.' : 'Der öffentliche Storage-Link fehlt oder ist kein Symlink.');

    $environmentFindings = [];
    $environmentWarnings = [];
    $appUrl = (string) config('app.url');
    $appUrlScheme = parse_url($appUrl, PHP_URL_SCHEME);
    $isProduction = config('app.env') === 'production';

    if ($isProduction && config('app.debug')) {
        $environmentFindings[] = 'APP_DEBUG ist in Produktion aktiv.';
    }

    if ($isProduction && $appUrlScheme !== 'https') {
        $environmentFindings[] = 'APP_URL nutzt in Produktion kein HTTPS.';
    }

    if ($isProduction && ! config('session.secure')) {
        $environmentFindings[] = 'SESSION_SECURE_COOKIE ist in Produktion nicht aktiv.';
    }

    if (! config('session.http_only')) {
        $environmentFindings[] = 'SESSION_HTTP_ONLY ist deaktiviert.';
    }

    $sameSite = strtolower((string) config('session.same_site'));

    if (! in_array($sameSite, ['lax', 'strict'], true)) {
        $environmentWarnings[] = 'SESSION_SAME_SITE sollte lax oder strict sein.';
    }

    if ($environmentFindings !== []) {
        $add('critical', 'Produktionskonfiguration unsicher', 'Wichtige Sicherheitswerte sind für den Livebetrieb nicht korrekt gesetzt.', $environmentFindings);
    } elseif ($environmentWarnings !== []) {
        $add('warning', 'Produktionskonfiguration prüfen', 'Die Konfiguration ist lauffähig, sollte aber vor einem Release geprüft werden.', $environmentWarnings);
    } else {
        $add('ok', 'Produktionskonfiguration', 'APP-URL, Debug-Modus und Session-Cookies wirken sicher konfiguriert.');
    }

    try {
        $migrator = app(\Illuminate\Database\Migrations\Migrator::class);
        $repository = $migrator->getRepository();
        $pendingMigrationRisks = [];

        if ($repository->repositoryExists()) {
            $ranMigrations = collect($repository->getRan())->flip();
            $migrationFiles = $migrator->getMigrationFiles(database_path('migrations'));

            $destructivePatterns = [
                '/Schema::drop(?:IfExists)?\s*\(/i' => 'löscht eine Tabelle',
                '/->drop(?:Column|Columns|ConstrainedForeignId|Foreign|Index|Unique|Primary|Morphs|RememberToken|SoftDeletes|Timestamps)\s*\(/i' => 'löscht Tabellenstruktur',
                '/\btruncate\s*\(/i' => 'leert Datensätze',
                '/->delete\s*\(/i' => 'löscht Datensätze',
                '/DB::(?:statement|unprepared)\s*\([^;]*(?:drop|truncate|delete)\b/is' => 'führt riskantes SQL aus',
            ];

            foreach ($migrationFiles as $migrationName => $path) {
                if ($ranMigrations->has($migrationName)) {
                    continue;
                }

                $contents = File::get($path);
                $upPosition = strpos($contents, 'function up');
                $downPosition = $upPosition === false ? false : strpos($contents, 'function down', $upPosition);
                $upSection = $upPosition === false
                    ? $contents
                    : substr($contents, $upPosition, $downPosition === false ? null : $downPosition - $upPosition);

                foreach ($destructivePatterns as $pattern => $description) {
                    if (preg_match($pattern, $upSection) === 1) {
                        $pendingMigrationRisks[] = basename($path) . ': ' . $description;
                    }
                }
            }
        }

        if ($pendingMigrationRisks !== []) {
            $add('critical', 'Riskante ausstehende Migrationen', 'Vor dem Deployment bitte prüfen: Mindestens eine noch nicht ausgeführte Migration könnte Daten entfernen.', array_values(array_unique($pendingMigrationRisks)));
        } else {
            $add('ok', 'Ausstehende Migrationen geprüft', 'Keine riskanten Datenoperationen in noch nicht ausgeführten Migrationen gefunden.');
        }
    } catch (\Throwable $exception) {
        $add('warning', 'Migrationen nicht prüfbar', 'Die Migrationsprüfung konnte nicht vollständig ausgeführt werden: ' . $exception->getMessage());
    }

    $criticalCount = collect($checks)->where('level', 'critical')->count();
    $warningCount = collect($checks)->where('level', 'warning')->count();

    if ($this->option('json')) {
        $this->line(json_encode([
            'status' => $criticalCount > 0 ? 'failed' : ($warningCount > 0 ? 'warning' : 'ok'),
            'critical' => $criticalCount,
            'warning' => $warningCount,
            'checks' => $checks,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $criticalCount > 0 ? 1 : 0;
    }

    $this->components->info('Clubano Sicherheitscheck');

    foreach ($checks as $check) {
        $line = "{$check['title']}: {$check['message']}";

        match ($check['level']) {
            'critical' => $this->components->error($line),
            'warning' => $this->components->warn($line),
            default => $this->components->info($line),
        };

        foreach ($check['items'] as $item) {
            $this->line('  - ' . $item);
        }
    }

    $this->newLine();

    if ($criticalCount > 0) {
        $this->components->error("Nicht releasebereit: {$criticalCount} kritische Auffälligkeit(en), {$warningCount} Warnung(en).");

        return 1;
    }

    $this->components->info("Releasecheck bestanden: 0 kritische Auffälligkeiten, {$warningCount} Warnung(en).");

    return 0;
})->purpose('Prüft vor Releases auf versehentliche Webroot-Leaks, Dumps und Storage-Auffälligkeiten');

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
})->purpose('Berechnet die aktuellen Salden aller Konten aus Buchungen und Entwürfen neu');

Artisan::command('clubano:migrate-private-files {--dry-run : Zeigt nur an, was geändert würde} {--delete-public : Entfernt öffentliche Quelldateien nach erfolgreichem Kopieren}', function (ReceiptStorage $receiptStorage) {
    $dryRun = (bool) $this->option('dry-run');
    $deletePublic = (bool) $this->option('delete-public');

    $documentCount = Document::withoutGlobalScopes()->where('disk', 'public')->count();
    $transactionReceipts = Transaction::withoutGlobalScopes()
        ->whereNotNull('receipt_file')
        ->where('receipt_file', 'not like', ReceiptStorage::PRIVATE_PREFIX . '%')
        ->get();
    $bankReceipts = BankTransaction::withoutGlobalScopes()
        ->whereNotNull('receipt_file')
        ->where('receipt_file', 'not like', ReceiptStorage::PRIVATE_PREFIX . '%')
        ->get();

    $this->components->info('Gefunden: ' . $documentCount . ' Dokument(e), ' . $transactionReceipts->count() . ' Buchungsbeleg(e), ' . $bankReceipts->count() . ' Bankimport-Beleg(e).');

    if ($dryRun) {
        $this->components->warn('Trockenlauf: Es werden keine Dateien kopiert und keine Daten geändert.');
    }

    $migrated = 0;
    $missing = 0;

    foreach (Document::withoutGlobalScopes()->where('disk', 'public')->orderBy('id')->cursor() as $document) {
        $sourcePath = ltrim((string) $document->path, '/');
        if ($sourcePath === '' || ! Storage::disk('public')->exists($sourcePath)) {
            $missing++;
            $this->warn('Dokument #' . $document->id . ': Quelldatei fehlt.');
            continue;
        }

        $targetPath = $receiptStorage->privateTargetPath('documents', (int) $document->tenant_id, $sourcePath);
        $targetPath = $receiptStorage->uniqueLocalPath($targetPath);

        if ($dryRun) {
            $this->line('Dokument #' . $document->id . ': public:' . $sourcePath . ' -> local:' . $targetPath);
            continue;
        }

        $receiptStorage->copyPublicToLocal($sourcePath, $targetPath);
        $document->forceFill(['disk' => 'local', 'path' => $targetPath])->save();
        if ($deletePublic) {
            Storage::disk('public')->delete($sourcePath);
        }
        $migrated++;
    }

    foreach ([
        'Buchungsbeleg' => $transactionReceipts,
        'Bankimport-Beleg' => $bankReceipts,
    ] as $label => $records) {
        foreach ($records as $record) {
            $sourcePath = ltrim((string) $record->receipt_file, '/');
            if ($sourcePath === '' || ! Storage::disk('public')->exists($sourcePath)) {
                $missing++;
                $this->warn($label . ' #' . $record->id . ': Quelldatei fehlt.');
                continue;
            }

            $targetPath = $receiptStorage->privateTargetPath('receipts', (int) $record->tenant_id, $sourcePath);
            $targetPath = $receiptStorage->uniqueLocalPath($targetPath);

            if ($dryRun) {
                $this->line($label . ' #' . $record->id . ': public:' . $sourcePath . ' -> local:' . $targetPath);
                continue;
            }

            $receiptStorage->copyPublicToLocal($sourcePath, $targetPath);
            $record->forceFill(['receipt_file' => ReceiptStorage::PRIVATE_PREFIX . $targetPath])->save();
            if ($deletePublic) {
                Storage::disk('public')->delete($sourcePath);
            }
            $migrated++;
        }
    }

    $this->newLine();
    $this->components->info($dryRun ? 'Trockenlauf abgeschlossen.' : 'Migration abgeschlossen.');
    $this->line('Migriert: ' . $migrated);
    $this->line('Fehlende Quelldateien: ' . $missing);

    return 0;
})->purpose('Verschiebt öffentliche Dokumente und Belege kontrolliert in den privaten Laravel-Speicher');

Artisan::command('clubano:sanitize-stored-html {--apply : Bereinigt die gefundenen Inhalte wirklich}', function (HtmlSanitizer $htmlSanitizer) {
    $apply = (bool) $this->option('apply');
    $targets = [
        [Template::class, 'body', 'Vorlagen'],
        [Event::class, 'description', 'Veranstaltungen'],
        [Protocol::class, 'content', 'Protokolle'],
        [PublicForm::class, 'confirmation_mail_body', 'Formular-Bestätigungsmails'],
        [Tenant::class, 'member_exit_mail_body', 'Austrittsmails'],
        [Tenant::class, 'donation_email_body', 'Spendenmails'],
        [Tenant::class, 'voucher_mail_body', 'Gutscheinmails'],
        [OperatorAnnouncement::class, 'body_html', 'Betreiber-Mitteilungen'],
    ];

    $this->components->info($apply
        ? 'Bereinige gespeicherte HTML-Inhalte.'
        : 'Trockenlauf: Es werden nur betroffene gespeicherte HTML-Inhalte gezählt.');

    $totalChanged = 0;

    foreach ($targets as [$modelClass, $field, $label]) {
        $changed = 0;

        $modelClass::withoutGlobalScopes()
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->orderBy('id')
            ->cursor()
            ->each(function ($record) use ($htmlSanitizer, $field, $apply, &$changed) {
                $current = (string) $record->{$field};
                $sanitized = $htmlSanitizer->sanitize($current) ?? '';

                if ($sanitized === $current) {
                    return;
                }

                $changed++;

                if ($apply) {
                    $record->forceFill([$field => $sanitized])->save();
                }
            });

        $totalChanged += $changed;
        $this->line($label . ': ' . $changed . ' betroffene Datensätze');
    }

    $this->newLine();
    $this->components->info($apply
        ? 'HTML-Bereinigung abgeschlossen. Geänderte Datensätze: ' . $totalChanged
        : 'Trockenlauf abgeschlossen. Potenziell zu bereinigende Datensätze: ' . $totalChanged);

    return 0;
})->purpose('Prüft und bereinigt bestehende gespeicherte Editor-HTML-Inhalte');
