<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

function createTransactionSearchTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'Buchungsverein ' . Str::random(5),
        'slug' => 'buchungsverein-' . Str::random(8),
        'email' => 'buchung-' . Str::random(5) . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $user];
}

test('treasurers can search from and to accounts while editing a transaction', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'chart_name' => 'SKR-Test',
        'active' => true,
        'online' => false,
    ]);

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8006',
        'name' => 'Mitgliedsbeiträge',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'chart_name' => 'SKR-Test',
        'active' => true,
        'online' => false,
    ]);

    $transaction = Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => now()->toDateString(),
        'description' => 'Testbuchung',
        'amount' => 75,
        'account_from_id' => $income->id,
        'account_to_id' => $bank->id,
        'tax_area' => 'ideell',
        'receipt_number' => 'TRX-TEST-1',
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertSee('Von-Konto suchen, z. B. 1200 oder Bank')
        ->assertSee('Nach-Konto suchen, z. B. 8006 oder Beitrag')
        ->assertSee('1200 - Bank')
        ->assertSee('8006 - Mitgliedsbeiträge')
        ->assertSee('SKR-Test');
});

test('editing a transaction recalculates old and new account balances', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8006',
        'name' => 'Mitgliedsbeiträge',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_start' => 0,
        'balance_current' => 0,
    ]);

    $oldBank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank alt',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_start' => 0,
        'balance_current' => 999,
    ]);

    $newBank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1210',
        'name' => 'Bank neu',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_start' => 0,
        'balance_current' => 999,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => now()->subDay()->toDateString(),
        'description' => 'Abgeschlossene Zahlung',
        'amount' => 50,
        'account_from_id' => $income->id,
        'account_to_id' => $oldBank->id,
        'tax_area' => 'ideell',
        'receipt_number' => 'TRX-FINAL-1',
        'status' => 'abgeschlossen',
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    $draft = Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => now()->toDateString(),
        'description' => 'Offene Buchung',
        'amount' => 25,
        'account_from_id' => $income->id,
        'account_to_id' => $oldBank->id,
        'tax_area' => 'ideell',
        'receipt_number' => 'TRX-DRAFT-1',
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->put(route('transactions.update', $draft), [
            'date' => now()->toDateString(),
            'description' => 'Offene Buchung korrigiert',
            'amount' => 25,
            'account_from_id' => $income->id,
            'account_to_id' => $newBank->id,
            'tax_area' => 'ideell',
        ])
        ->assertRedirect(route('transactions.index'));

    expect((float) $oldBank->refresh()->balance_current)->toBe(50.0)
        ->and((float) $newBank->refresh()->balance_current)->toBe(0.0)
        ->and((float) $income->refresh()->balance_current)->toBe(-50.0);
});

test('finalizing an income transaction updates the target bank account balance', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8003',
        'name' => 'Erlöse Maimarkt',
        'type' => 'einnahme',
        'tax_area' => 'zweckbetrieb',
        'active' => true,
        'online' => false,
        'balance_start' => 0,
        'balance_current' => 0,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_start' => 100,
        'balance_current' => 100,
    ]);

    $transaction = Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => now()->toDateString(),
        'description' => 'Erlöse Maimarkt',
        'amount' => 280.38,
        'account_from_id' => $income->id,
        'account_to_id' => $bank->id,
        'tax_area' => 'zweckbetrieb',
        'receipt_number' => 'TRX-MAIMARKT-1',
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.finalize', $transaction))
        ->assertSessionHas('success', 'Die Buchung wurde abgeschlossen.');

    expect($transaction->refresh()->status)->toBe('abgeschlossen')
        ->and((float) $bank->refresh()->balance_current)->toBe(380.38)
        ->and((float) $income->refresh()->balance_current)->toBe(-280.38);
});

test('account balances can be recalculated from finalized transactions', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8003',
        'name' => 'Erlöse Maimarkt',
        'type' => 'einnahme',
        'tax_area' => 'zweckbetrieb',
        'active' => true,
        'online' => false,
        'balance_start' => 0,
        'balance_current' => 999,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_start' => 100,
        'balance_current' => 999,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => now()->toDateString(),
        'description' => 'Erlöse Maimarkt',
        'amount' => 280.38,
        'account_from_id' => $income->id,
        'account_to_id' => $bank->id,
        'tax_area' => 'zweckbetrieb',
        'receipt_number' => 'TRX-RECALC-1',
        'status' => 'abgeschlossen',
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    expect(Artisan::call('clubano:recalculate-account-balances', [
        'tenantId' => $tenant->id,
    ]))->toBe(0);

    expect((float) $bank->refresh()->balance_current)->toBe(380.38)
        ->and((float) $income->refresh()->balance_current)->toBe(-280.38);
});

test('transactions can use a contract as recurring receipt evidence', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $rent = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '4210',
        'name' => 'Miete Vereinsheim',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => '2026-08-01',
            'description' => 'Miete Vereinsheim August',
            'amount' => 450,
            'account_from_id' => $bank->id,
            'account_to_id' => $rent->id,
            'status' => 'entwurf',
            'tax_area' => 'ideell',
            'receipt_kind' => 'vertrag',
            'contract_reference' => 'Mietvertrag Vereinsheim',
            'contract_location' => 'Dokumente / Verträge',
            'contract_date' => '2026-01-01',
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('description', 'Miete Vereinsheim August')
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->receipt_kind)->toBe('vertrag')
        ->and($transaction->hasAnyReceipt())->toBeTrue()
        ->and($transaction->receiptEvidenceDetail())->toBe('Mietvertrag Vereinsheim · Dokumente / Verträge');

    $this->actingAs($user)
        ->get(route('transactions.index', ['filter' => 'missing_receipt']))
        ->assertOk()
        ->assertDontSee('Miete Vereinsheim August');
});

test('missing receipts can be bulk marked as contract evidence', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $rent = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '4210',
        'name' => 'Miete Vereinsheim',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $august = Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => '2026-08-01',
        'description' => 'Miete August',
        'amount' => 450,
        'account_from_id' => $bank->id,
        'account_to_id' => $rent->id,
        'tax_area' => 'ideell',
        'receipt_number' => 'TRX-MIETE-08',
        'status' => 'abgeschlossen',
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    $september = Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => '2026-09-01',
        'description' => 'Miete September',
        'amount' => 450,
        'account_from_id' => $bank->id,
        'account_to_id' => $rent->id,
        'tax_area' => 'ideell',
        'receipt_number' => 'TRX-MIETE-09',
        'status' => 'entwurf',
    ]);

    $withReceipt = Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => '2026-10-01',
        'description' => 'Miete Oktober',
        'amount' => 450,
        'account_from_id' => $bank->id,
        'account_to_id' => $rent->id,
        'tax_area' => 'ideell',
        'receipt_number' => 'TRX-MIETE-10',
        'receipt_file' => 'receipts/test/beleg.pdf',
        'receipt_kind' => 'upload',
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.index', ['filter' => 'missing_receipt']))
        ->post(route('transactions.contract-receipt-selected'), [
            'transaction_ids' => [$august->id, $september->id, $withReceipt->id],
            'contract_reference' => 'Mietvertrag Vereinsheim',
            'contract_location' => 'Dokumente / Verträge',
            'contract_date' => '2026-01-01',
        ])
        ->assertRedirect(route('transactions.index', ['filter' => 'missing_receipt']))
        ->assertSessionHas('success', '2 Buchung(en) wurden als Vertrag/Dauerbeleg markiert.');

    expect($august->refresh()->receipt_kind)->toBe('vertrag')
        ->and($september->refresh()->receipt_kind)->toBe('vertrag')
        ->and($withReceipt->refresh()->receipt_kind)->toBe('upload');
});
