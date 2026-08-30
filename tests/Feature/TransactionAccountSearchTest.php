<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
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

test('treasurers can open and download the printable booking journal', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $cash = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1000',
        'name' => 'Kasse',
        'type' => 'kasse',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $expense = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '4930',
        'name' => 'Bürobedarf',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => '2026-08-24',
        'description' => 'Briefmarken für Vereinsversand',
        'amount' => 12.50,
        'account_from_id' => $cash->id,
        'account_to_id' => $expense->id,
        'tax_area' => 'ideell',
        'receipt_number' => 'TRX-JOURNAL-1',
        'status' => 'abgeschlossen',
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.journal', ['year' => 2026, 'month' => 8]))
        ->assertOk()
        ->assertSee('Buchungsjournal')
        ->assertSee('DIN A4 im Querformat')
        ->assertSee('Briefmarken für Vereinsversand');

    $response = $this->actingAs($user)
        ->get(route('transactions.journal.pdf', ['year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('treasurers can open and download the cashbook print layout', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $cash = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1000',
        'name' => 'Vereinskasse',
        'type' => 'kasse',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_start' => 100,
        'balance_current' => 100,
    ]);

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8003',
        'name' => 'Erlöse Sommerfest',
        'type' => 'einnahme',
        'tax_area' => 'zweckbetrieb',
        'active' => true,
        'online' => false,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'date' => '2026-08-25',
        'description' => 'Barverkauf Sommerfest',
        'amount' => 79,
        'account_from_id' => $income->id,
        'account_to_id' => $cash->id,
        'tax_area' => 'zweckbetrieb',
        'receipt_number' => 'KB-001',
        'status' => 'abgeschlossen',
        'finalized_at' => now(),
        'finalized_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.cashbook.print', ['account' => $cash->id, 'year' => 2026, 'month' => 8]))
        ->assertOk()
        ->assertSee('Kassenbuch')
        ->assertSee('Vereinskasse')
        ->assertSee('Barverkauf Sommerfest');

    $response = $this->actingAs($user)
        ->get(route('transactions.cashbook.pdf', ['account' => $cash->id, 'year' => 2026, 'month' => 8]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
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
        ->and((float) $newBank->refresh()->balance_current)->toBe(25.0)
        ->and((float) $income->refresh()->balance_current)->toBe(-75.0);
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

test('saving a draft transaction updates visible account balances', function () {
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

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'description' => 'Offener Zahlungseingang',
            'amount' => 42.50,
            'account_from_id' => $income->id,
            'account_to_id' => $bank->id,
            'status' => 'entwurf',
            'tax_area' => 'zweckbetrieb',
        ])
        ->assertRedirect(route('transactions.index'));

    expect((float) $bank->refresh()->balance_current)->toBe(142.5)
        ->and((float) $income->refresh()->balance_current)->toBe(-42.5);
});

test('saving a draft cash expense updates visible cash account balance', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createTransactionSearchTenant();

    $cash = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1000',
        'name' => 'Kasse',
        'type' => 'kasse',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_start' => 200,
        'balance_current' => 200,
    ]);

    $expense = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '4930',
        'name' => 'Bürobedarf',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_start' => 0,
        'balance_current' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'description' => 'Briefmarken bar bezahlt',
            'amount' => 12.50,
            'account_from_id' => $cash->id,
            'account_to_id' => $expense->id,
            'status' => 'entwurf',
            'tax_area' => 'ideell',
        ])
        ->assertRedirect(route('transactions.index'));

    expect((float) $cash->refresh()->balance_current)->toBe(187.5)
        ->and((float) $expense->refresh()->balance_current)->toBe(12.5);
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

test('transactions can reuse a stored contract document as receipt evidence', function () {
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

    $contract = Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
        'title' => 'Mietvertrag Vereinsheim',
        'category' => Document::CATEGORY_CONTRACTS,
        'status' => Document::STATUS_ACTIVE,
        'document_date' => '2026-01-01',
        'disk' => 'local',
        'path' => 'documents/test/mietvertrag.pdf',
        'original_name' => 'mietvertrag.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1234,
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
            'contract_document_id' => $contract->id,
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('description', 'Miete Vereinsheim August')
        ->firstOrFail();

    expect($transaction->receipt_kind)->toBe('vertrag')
        ->and($transaction->receipt_meta['contract_document_id'])->toBe($contract->id)
        ->and($transaction->receipt_meta['contract_reference'])->toBe('Mietvertrag Vereinsheim')
        ->and($transaction->receiptEvidenceDetail())->toBe('Mietvertrag Vereinsheim · Dokumentenablage / Verträge');
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

test('finalized transaction linked to an invoice counts as receipt and pays the invoice', function () {
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
        'balance_start' => 0,
        'balance_current' => 0,
    ]);

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

    $invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'document_type' => 'invoice',
        'income_account_id' => $income->id,
        'recipient_type' => 'free',
        'recipient_name' => 'Max Muster',
        'recipient_email' => 'max@example.test',
        'invoice_number' => 'R-TRX-001',
        'invoice_date' => '2026-08-01',
        'due_date' => '2026-08-15',
        'status' => 'open',
        'discount' => 0,
        'tax_rate' => 0,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Mitgliedsbeitrag',
        'quantity' => 1,
        'unit' => 'Pauschale',
        'unit_price' => 100,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => '2026-08-10',
            'description' => 'Zahlung Max Muster',
            'amount' => 100,
            'account_from_id' => $income->id,
            'account_to_id' => $bank->id,
            'invoice_id' => $invoice->id,
            'status' => 'abgeschlossen',
            'tax_area' => 'ideell',
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('description', 'Zahlung Max Muster')
        ->firstOrFail();

    $payment = Payment::query()
        ->where('tenant_id', $tenant->id)
        ->where('transaction_id', $transaction->id)
        ->firstOrFail();

    expect($transaction->invoice_id)->toBe($invoice->id)
        ->and($transaction->hasAnyReceipt())->toBeTrue()
        ->and($transaction->receiptEvidenceDetail())->toBe('Rechnung R-TRX-001')
        ->and((float) $payment->amount)->toBe(100.0)
        ->and($invoice->refresh()->status)->toBe('paid')
        ->and($invoice->paid_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('transactions.index', ['filter' => 'missing_receipt']))
        ->assertOk()
        ->assertDontSee('Zahlung Max Muster');
});

test('draft transaction linked to an invoice pays it only after finalizing', function () {
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

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '8006',
        'name' => 'Mitgliedsbeiträge',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'document_type' => 'invoice',
        'income_account_id' => $income->id,
        'recipient_type' => 'free',
        'recipient_name' => 'Erika Muster',
        'recipient_email' => 'erika@example.test',
        'invoice_number' => 'R-TRX-002',
        'invoice_date' => '2026-08-01',
        'due_date' => '2026-08-15',
        'status' => 'open',
        'discount' => 0,
        'tax_rate' => 0,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Mitgliedsbeitrag',
        'quantity' => 1,
        'unit' => 'Pauschale',
        'unit_price' => 80,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => '2026-08-10',
            'description' => 'Zahlung Erika Muster',
            'amount' => 80,
            'account_from_id' => $income->id,
            'account_to_id' => $bank->id,
            'invoice_id' => $invoice->id,
            'status' => 'entwurf',
            'tax_area' => 'ideell',
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('description', 'Zahlung Erika Muster')
        ->firstOrFail();

    expect(Payment::query()->where('transaction_id', $transaction->id)->exists())->toBeFalse()
        ->and($invoice->refresh()->status)->toBe('open');

    $this->actingAs($user)
        ->post(route('transactions.finalize', $transaction))
        ->assertSessionHas('success', 'Die Buchung wurde abgeschlossen.');

    expect(Payment::query()->where('transaction_id', $transaction->id)->exists())->toBeTrue()
        ->and($invoice->refresh()->status)->toBe('paid');
});
