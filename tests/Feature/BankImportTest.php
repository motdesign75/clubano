<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createFinanceTenant(string $suffix): array
{
    $tenant = Tenant::create([
        'name' => 'Bankimport Verein ' . $suffix,
        'slug' => 'bankimport-' . $suffix,
        'email' => 'bankimport-' . $suffix . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
    ]);

    return [$tenant, $user];
}

test('finance users can import csv bank statements and duplicates are skipped', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenant('csv');

    $bankAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'is_postable' => true,
    ]);

    $csv = "Buchungstag;Betrag;Währung;Name;Verwendungszweck;Referenz\n"
        . "24.08.2026;79,00;EUR;Max Muster;Braukurs;ABC123\n";

    $response = $this->actingAs($user)->post(route('bank-imports.store'), [
        'account_id' => $bankAccount->id,
        'statement_file' => UploadedFile::fake()->createWithContent('umsatz.csv', $csv),
    ]);

    $response->assertRedirect();
    expect(BankTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);

    $secondResponse = $this->actingAs($user)->post(route('bank-imports.store'), [
        'account_id' => $bankAccount->id,
        'statement_file' => UploadedFile::fake()->createWithContent('umsatz.csv', $csv),
    ]);

    $secondResponse->assertRedirect();
    expect(BankTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('assigned bank transactions create draft bookings and update account balances', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenant('booking');

    $bankAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'is_postable' => true,
        'balance_start' => 100,
    ]);

    $incomeAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '4300',
        'name' => 'Veranstaltungserlöse',
        'type' => 'einnahme',
        'tax_area' => 'zweckbetrieb',
        'active' => true,
        'is_postable' => true,
    ]);

    $bankImport = BankImport::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'account_id' => $bankAccount->id,
        'uploaded_by' => $user->id,
        'filename' => 'umsatz.csv',
        'format' => 'CSV',
        'status' => 'review',
        'row_count' => 1,
        'imported_count' => 1,
    ]);

    $bankTransaction = BankTransaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'bank_import_id' => $bankImport->id,
        'account_id' => $bankAccount->id,
        'booking_date' => '2026-08-24',
        'amount' => 79,
        'currency' => 'EUR',
        'direction' => 'credit',
        'counterparty_name' => 'Max Muster',
        'purpose' => 'Braukurs',
        'fingerprint' => 'booking-test',
        'status' => BankTransaction::STATUS_PENDING,
    ]);

    $this->actingAs($user)->patch(route('bank-imports.transactions.update', $bankTransaction), [
        'source_account_id' => $bankAccount->id,
        'selected_account_id' => $incomeAccount->id,
    ])->assertRedirect();

    $this->actingAs($user)->post(route('bank-imports.transactions.book', $bankTransaction))->assertRedirect();

    $transaction = Transaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe('entwurf');
    expect((float) $transaction->amount)->toBe(79.0);
    expect($transaction->account_from_id)->toBe($incomeAccount->id);
    expect($transaction->account_to_id)->toBe($bankAccount->id);
    expect(BankTransaction::withoutGlobalScopes()->find($bankTransaction->id)->status)->toBe(BankTransaction::STATUS_BOOKED);
    expect((float) $bankAccount->fresh()->balance_current)->toBe(179.0);
});

test('bank transactions can carry optional contract receipts into created bookings', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('public');

    [$tenant, $user] = createFinanceTenant('receipt');

    $bankAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'is_postable' => true,
    ]);

    $expenseAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '6300',
        'name' => 'Miete',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'is_postable' => true,
    ]);

    $bankImport = BankImport::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'account_id' => $bankAccount->id,
        'uploaded_by' => $user->id,
        'filename' => 'umsatz.csv',
        'format' => 'CSV',
        'status' => 'review',
        'row_count' => 1,
        'imported_count' => 1,
    ]);

    $bankTransaction = BankTransaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'bank_import_id' => $bankImport->id,
        'account_id' => $bankAccount->id,
        'booking_date' => '2026-08-24',
        'amount' => -250,
        'currency' => 'EUR',
        'direction' => 'debit',
        'counterparty_name' => 'Vermieter GmbH',
        'purpose' => 'Miete Vereinsheim August',
        'fingerprint' => 'contract-receipt-test',
        'status' => BankTransaction::STATUS_PENDING,
    ]);

    $this->actingAs($user)->patch(route('bank-imports.transactions.update', $bankTransaction), [
        'source_account_id' => $bankAccount->id,
        'selected_account_id' => $expenseAccount->id,
        'receipt_kind' => 'vertrag',
        'receipt_file' => UploadedFile::fake()->create('mietvertrag.pdf', 12, 'application/pdf'),
        'contract_reference' => 'Mietvertrag Vereinsheim',
        'contract_location' => 'Clubano Bankimport',
        'contract_date' => '2026-01-01',
    ])->assertRedirect();

    $bankTransaction = BankTransaction::withoutGlobalScopes()->find($bankTransaction->id);

    expect($bankTransaction->receipt_kind)->toBe('vertrag');
    Storage::disk('public')->assertExists($bankTransaction->receipt_file);

    $this->actingAs($user)->post(route('bank-imports.transactions.book', $bankTransaction))->assertRedirect();

    $transaction = Transaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($transaction->receipt_kind)->toBe('vertrag');
    expect($transaction->receipt_file)->toBe($bankTransaction->receipt_file);
    expect($transaction->receipt_meta['contract_reference'])->toBe('Mietvertrag Vereinsheim');
    expect($transaction->receipt_meta['source'])->toBe('Bankumsatz-Import');
});
