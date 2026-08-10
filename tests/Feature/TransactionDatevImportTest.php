<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

function createDatevTenantWithUser(string $suffix): array
{
    $tenant = Tenant::create([
        'name' => 'DATEV Verein ' . $suffix,
        'slug' => 'datev-verein-' . $suffix . '-' . Str::random(5),
        'email' => 'datev-' . $suffix . '-' . Str::random(5) . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $user];
}

function datevUpload(string $csv, string $name = 'EXTF_Buchungsstapel.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'datev-import-');
    file_put_contents($path, $csv);

    return new UploadedFile($path, $name, 'text/csv', null, true);
}

test('treasurers can import DATEV booking stacks and assign debit and credit accounts', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createDatevTenantWithUser('booking-import');

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1220',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $memberReceivable = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '10013',
        'name' => 'Debitor Roger',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8006',
        'name' => 'Mitgliedsbeiträge',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $csv = "\xEF\xBB\xBF\"EXTF\";\"700\";\"21\";\"Buchungsstapel\";\"13\";\"20260805162610000\";\"\";\"\";\"\";\"\";\"\";\"\";\"20260101\";\"4\";\"20260101\";\"20261231\";\"Demo\";\"\";\"1\";\"0\";\"0\";\"EUR\"\n"
        . "\"Umsatz (ohne Soll/Haben-Kz)\";\"Soll/Haben-Kennzeichen\";\"WKZ Umsatz\";\"Kurs\";\"Basis-Umsatz\";\"WKZ Basis-Umsatz\";\"Konto\";\"Gegenkonto (ohne BU-Schlüssel)\";\"BU-Schlüssel\";\"Belegdatum\";\"Belegfeld 1\";\"Belegfeld 2\";\"Skonto\";\"Buchungstext\";\"Buchungs GUID\"\n"
        . "\"75,00\";\"S\";\"\";\"\";\"\";\"\";\"1220\";\"10013\";\"\";\"0201\";\"BE-1\";\"\";\"\";\"Eingangszahlung\";\"GUID-1\"\n"
        . "\"75,00\";\"H\";\"\";\"\";\"\";\"\";\"8006\";\"10013\";\"\";\"0301\";\"RE-1\";\"\";\"\";\"Beitragsrechnung\";\"GUID-2\"\n";

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->post(route('transactions.datev-import'), [
            'datev_file' => datevUpload($csv),
            'status' => 'entwurf',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('transactions.index'));

    $transactions = Transaction::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->orderBy('date')
        ->get();

    expect($transactions)->toHaveCount(2)
        ->and($transactions[0]->account_to_id)->toBe($bank->id)
        ->and($transactions[0]->account_from_id)->toBe($memberReceivable->id)
        ->and($transactions[0]->amount)->toEqual('75.00')
        ->and($transactions[0]->receipt_number)->toBe('DATEV-20260805162610000-00003')
        ->and($transactions[1]->account_from_id)->toBe($income->id)
        ->and($transactions[1]->account_to_id)->toBe($memberReceivable->id)
        ->and($transactions[1]->date->toDateString())->toBe('2026-01-03');
});

test('DATEV booking import skips already imported rows', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createDatevTenantWithUser('booking-reimport');

    foreach (['1220' => 'Bank', '10013' => 'Debitor'] as $number => $name) {
        Account::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'number' => $number,
            'name' => $name,
            'type' => $number === '1220' ? 'bank' : 'ausgabe',
            'tax_area' => 'ideell',
            'active' => true,
            'online' => false,
        ]);
    }

    $csv = "\xEF\xBB\xBF\"EXTF\";\"700\";\"21\";\"Buchungsstapel\";\"13\";\"20260805162610000\";\"\";\"\";\"\";\"\";\"\";\"\";\"20260101\";\"4\";\"20260101\";\"20261231\";\"Demo\"\n"
        . "\"Umsatz (ohne Soll/Haben-Kz)\";\"Soll/Haben-Kennzeichen\";\"Konto\";\"Gegenkonto (ohne BU-Schlüssel)\";\"Belegdatum\";\"Buchungstext\"\n"
        . "\"75,00\";\"S\";\"1220\";\"10013\";\"0201\";\"Eingangszahlung\"\n";

    $payload = [
        'datev_file' => datevUpload($csv, 'EXTF.csv'),
        'status' => 'entwurf',
    ];

    $this->actingAs($user)->from(route('transactions.index'))->post(route('transactions.datev-import'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($user)->from(route('transactions.index'))->post(route('transactions.datev-import'), [
        'datev_file' => datevUpload($csv, 'EXTF.csv'),
        'status' => 'entwurf',
    ])->assertSessionHasNoErrors();

    expect(Transaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});
