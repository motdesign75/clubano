<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

function createFinanceTenantWithUser(string $suffix): array
{
    $tenant = Tenant::create([
        'name' => 'Finanzverein ' . $suffix,
        'slug' => 'finanzverein-' . $suffix . '-' . Str::random(5),
        'email' => 'finanz-' . $suffix . '-' . Str::random(5) . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $user];
}

test('treasurers can create the simple Clubano account chart', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenantWithUser('simple-chart');

    $this->actingAs($user)
        ->post(route('accounts.simple-chart'))
        ->assertRedirect(route('accounts.index', ['tab' => 'erloes']));

    expect(Account::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(10)
        ->and(Account::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('number', '4000')->value('name'))->toBe('Mitgliedsbeiträge')
        ->and(Account::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('number', '4000')->value('chart_name'))->toBe('Clubano Standard');
});

test('treasurers can import a DATEV style account chart csv', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenantWithUser('import-chart');
    $csv = "\xEF\xBB\xBF\"Kontonummer\";\"Bezeichnung\";\"Steuerschlüssel\";\"Buchbar\";\"DATEV-Automatik\"\n"
        . "5;\"Rückständige fällige Einzahlungen\";;\"Nein\";\"Nein\"\n"
        . "8006;\"Mitgliedsbeiträge\";;\"Ja\";\"Nein\"\n"
        . "1576;\"Abziehbare Vorsteuer 19 %\";\"VS19\";\"Ja\";\"Ja\"\n";

    $file = UploadedFile::fake()->createWithContent('kontenrahmen.csv', $csv);

    $this->actingAs($user)
        ->post(route('accounts.import-chart'), [
            'chart_name' => 'SKR-Test',
            'chart_file' => $file,
        ])
        ->assertRedirect(route('accounts.index', ['tab' => 'erloes']));

    $accounts = Account::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->keyBy('number');

    expect($accounts)->toHaveCount(3)
        ->and($accounts['5']->is_postable)->toBeFalse()
        ->and($accounts['5']->active)->toBeFalse()
        ->and($accounts['8006']->type)->toBe('einnahme')
        ->and($accounts['1576']->tax_key)->toBe('VS19')
        ->and($accounts['1576']->datev_automatic)->toBeTrue()
        ->and($accounts['1576']->chart_name)->toBe('SKR-Test');
});

test('treasurers must confirm account chart imports when accounts already exist', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenantWithUser('import-chart-confirmation');

    Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $csv = "\xEF\xBB\xBF\"Kontonummer\";\"Bezeichnung\";\"Steuerschlüssel\";\"Buchbar\";\"DATEV-Automatik\"\n"
        . "8006;\"Mitgliedsbeiträge\";;\"Ja\";\"Nein\"\n";

    $this->actingAs($user)
        ->from(route('accounts.index'))
        ->post(route('accounts.import-chart'), [
            'chart_name' => 'SKR-Test',
            'chart_file' => UploadedFile::fake()->createWithContent('kontenrahmen.csv', $csv),
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHasErrors('confirm_existing_chart_import');

    expect(Account::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);

    $this->actingAs($user)
        ->post(route('accounts.import-chart'), [
            'chart_name' => 'SKR-Test',
            'chart_file' => UploadedFile::fake()->createWithContent('kontenrahmen.csv', $csv),
            'confirm_existing_chart_import' => '1',
        ])
        ->assertRedirect(route('accounts.index', ['tab' => 'erloes']));

    expect(Account::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(2);
});

test('account chart import page warns before importing over existing accounts', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenantWithUser('import-warning-view');

    Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertSee('Wichtige Warnung')
        ->assertSee('Ich habe verstanden, dass bereits Konten vorhanden sind')
        ->assertSee('Der Import kann bestehende Konten aktualisieren');
});

test('account chart import page does not require confirmation for the first chart', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $user] = createFinanceTenantWithUser('first-import-no-warning');

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertDontSee('Ich habe verstanden, dass bereits Konten vorhanden sind');
});

test('treasurers can hide and restore unused accounts', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenantWithUser('hide-account');

    $account = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8006',
        'name' => 'Mitgliedsbeiträge',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.hide', $account))
        ->assertRedirect(route('accounts.index', ['tab' => 'inaktiv']));

    expect($account->refresh()->active)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('accounts.restore', $account))
        ->assertRedirect(route('accounts.index', ['tab' => 'erloes']));

    expect($account->refresh()->active)->toBeTrue();
});

test('treasurers can hide multiple accounts at once', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenantWithUser('bulk-hide');

    $first = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8001',
        'name' => 'Erlöse Sommerfest',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $second = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8002',
        'name' => 'Erlöse Weihnachtsmarkt',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.bulk-visibility'), [
            'action' => 'hide',
            'account_ids' => [$first->id, $second->id],
        ])
        ->assertRedirect(route('accounts.index', ['tab' => 'inaktiv']));

    expect($first->refresh()->active)->toBeFalse()
        ->and($second->refresh()->active)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('accounts.bulk-visibility'), [
            'action' => 'restore',
            'account_ids' => [$first->id, $second->id],
        ])
        ->assertRedirect(route('accounts.index', ['tab' => 'erloes']));

    expect($first->refresh()->active)->toBeTrue()
        ->and($second->refresh()->active)->toBeTrue();
});

test('bank accounts can show a negative opening balance', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenantWithUser('negative-bank-balance');

    $response = $this->actingAs($user)
        ->post(route('accounts.store'), [
            'number' => '1200',
            'name' => 'Bank',
            'type' => 'bank',
            'tax_area' => 'ideell',
            'balance_start' => '-125.75',
            'active' => '1',
            'online' => '0',
        ]);

    $response->assertRedirect(route('accounts.index'));

    $account = Account::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('number', '1200')
        ->firstOrFail();

    expect($account->balance_start)->toBe(-125.75)
        ->and($account->balance_current)->toBe(-125.75);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertSee('-125,75 €')
        ->assertSee('text-rose-700');
});

test('account overview offers a search field for large account charts', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenantWithUser('account-search');

    Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8006',
        'name' => 'Mitgliedsbeiträge',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'chart_name' => 'SKR-Test',
        'active' => true,
        'online' => false,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertSee('Konto suchen, z. B. 1200, Bank, DATEV')
        ->assertSee('Mitgliedsbeiträge')
        ->assertSee('SKR-Test');
});
