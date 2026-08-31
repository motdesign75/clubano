<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

test('bank csv imports understand common counterparty and purpose columns', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenant('csv-bank-columns');

    $bankAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'is_postable' => true,
    ]);

    $csv = "Buchungstag;Betrag;Währung;Auftraggeber/Empfänger;Verwendungszweck 1;Verwendungszweck 2;IBAN Auftraggeber/Empfänger\n"
        . "24.08.2026;100,00;EUR;Max Muster;Rechnung R-20260824001;Braukurs;DE02120300000000202051\n";

    $this->actingAs($user)->post(route('bank-imports.store'), [
        'account_id' => $bankAccount->id,
        'statement_file' => UploadedFile::fake()->createWithContent('volksbank.csv', $csv),
    ])->assertRedirect();

    $bankTransaction = BankTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($bankTransaction->counterparty_name)->toBe('Max Muster');
    expect($bankTransaction->counterparty_iban)->toBe('DE02120300000000202051');
    expect($bankTransaction->purpose)->toBe('Rechnung R-20260824001 · Braukurs');
});

test('camt imports read nested counterparty names from xml', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenant('camt-name');

    $bankAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'is_postable' => true,
    ]);

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.08">
  <BkToCstmrStmt>
    <Stmt>
      <Ntry>
        <Amt Ccy="EUR">100.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <BookgDt><Dt>2026-08-24</Dt></BookgDt>
        <ValDt><Dt>2026-08-24</Dt></ValDt>
        <AcctSvcrRef>CAMT123</AcctSvcrRef>
        <NtryDtls>
          <TxDtls>
            <Refs><EndToEndId>NOTPROVIDED</EndToEndId></Refs>
            <RltdPties>
              <Dbtr>
                <Pty>
                  <Nm>Max Muster</Nm>
                </Pty>
              </Dbtr>
              <DbtrAcct>
                <Id><IBAN>DE02120300000000202051</IBAN></Id>
              </DbtrAcct>
            </RltdPties>
            <RmtInf>
              <Ustrd>Rechnung R-20260824001</Ustrd>
              <Ustrd>Braukurs</Ustrd>
            </RmtInf>
          </TxDtls>
        </NtryDtls>
      </Ntry>
    </Stmt>
  </BkToCstmrStmt>
</Document>
XML;

    $this->actingAs($user)->post(route('bank-imports.store'), [
        'account_id' => $bankAccount->id,
        'statement_file' => UploadedFile::fake()->createWithContent('camt.xml', $xml),
    ])->assertRedirect();

    $bankTransaction = BankTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($bankTransaction->counterparty_name)->toBe('Max Muster');
    expect($bankTransaction->counterparty_iban)->toBe('DE02120300000000202051');
    expect($bankTransaction->purpose)->toBe('Rechnung R-20260824001 · Braukurs');
});

test('bank import list uses purpose when no counterparty name exists', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenant('display-fallback');

    $bankAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
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

    BankTransaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'bank_import_id' => $bankImport->id,
        'account_id' => $bankAccount->id,
        'booking_date' => '2026-08-24',
        'amount' => 100,
        'currency' => 'EUR',
        'direction' => 'credit',
        'purpose' => 'Rechnung R-20260824001',
        'fingerprint' => 'display-fallback-test',
        'status' => BankTransaction::STATUS_PENDING,
    ]);

    $this->actingAs($user)->get(route('bank-imports.index'))
        ->assertOk()
        ->assertSee('Rechnung R-20260824001')
        ->assertSee('Name im Export nicht enthalten')
        ->assertDontSee('Ohne Namen');
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
    ])->assertRedirectContains('#bank-transaction-' . $bankTransaction->id);

    $this->actingAs($user)->post(route('bank-imports.transactions.book', $bankTransaction))
        ->assertRedirectContains('#bank-transaction-' . $bankTransaction->id);

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
    Storage::fake('local');

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
    ])->assertRedirectContains('#bank-transaction-' . $bankTransaction->id);

    $bankTransaction = BankTransaction::withoutGlobalScopes()->find($bankTransaction->id);

    expect($bankTransaction->receipt_kind)->toBe('vertrag');
    expect($bankTransaction->receipt_file)->toStartWith('private:');
    Storage::disk('local')->assertExists(substr($bankTransaction->receipt_file, strlen('private:')));

    $this->actingAs($user)->post(route('bank-imports.transactions.book', $bankTransaction))->assertRedirect();

    $transaction = Transaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($transaction->receipt_kind)->toBe('vertrag');
    expect($transaction->receipt_file)->toBe($bankTransaction->receipt_file);
    expect($transaction->receipt_meta['contract_reference'])->toBe('Mietvertrag Vereinsheim');
    expect($transaction->receipt_meta['source'])->toBe('Bankumsatz-Import');
});

test('saved bank transaction assignments can receive a receipt upload later', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    [$tenant, $user] = createFinanceTenant('later-receipt');

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
        'number' => '4930',
        'name' => 'Bürobedarf',
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
        'selected_account_id' => $expenseAccount->id,
        'booking_date' => '2026-08-24',
        'amount' => -18.5,
        'currency' => 'EUR',
        'direction' => 'debit',
        'counterparty_name' => 'Papierladen',
        'purpose' => 'Briefumschläge',
        'fingerprint' => 'later-receipt-test',
        'status' => BankTransaction::STATUS_READY,
    ]);

    $this->actingAs($user)->patch(route('bank-imports.transactions.update', $bankTransaction), [
        'source_account_id' => $bankAccount->id,
        'selected_account_id' => $expenseAccount->id,
        'receipt_kind' => 'none',
        'receipt_file' => UploadedFile::fake()->create('beleg.pdf', 100, 'application/pdf'),
    ])->assertRedirectContains('#bank-transaction-' . $bankTransaction->id);

    $bankTransaction = BankTransaction::withoutGlobalScopes()->find($bankTransaction->id);

    expect($bankTransaction->receipt_kind)->toBe('upload');
    expect($bankTransaction->receipt_file)->toStartWith('private:');
    Storage::disk('local')->assertExists(substr($bankTransaction->receipt_file, strlen('private:')));
});

test('bank transactions can use an existing invoice as internal receipt', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceTenant('invoice-receipt');

    $bankAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'is_postable' => true,
    ]);

    $incomeAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '4300',
        'name' => 'Kurse',
        'type' => 'einnahme',
        'tax_area' => 'zweckbetrieb',
        'active' => true,
        'is_postable' => true,
    ]);

    $invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'document_type' => 'invoice',
        'income_account_id' => $incomeAccount->id,
        'recipient_name' => 'Max Muster',
        'recipient_email' => 'max@example.test',
        'invoice_number' => 'R-BANK-001',
        'invoice_date' => '2026-08-20',
        'due_date' => '2026-08-31',
        'status' => 'open',
        'amount' => 100,
        'total' => 100,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Braukurs',
        'quantity' => 1,
        'unit_price' => 100,
        'tax_rate' => 0,
    ]);

    $bankImport = BankImport::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'account_id' => $bankAccount->id,
        'uploaded_by' => $user->id,
        'filename' => 'umsatz.xml',
        'format' => 'CAMT.053',
        'status' => 'review',
        'row_count' => 1,
        'imported_count' => 1,
    ]);

    $bankTransaction = BankTransaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'bank_import_id' => $bankImport->id,
        'account_id' => $bankAccount->id,
        'booking_date' => '2026-08-24',
        'amount' => 100,
        'currency' => 'EUR',
        'direction' => 'credit',
        'counterparty_name' => 'Max Muster',
        'purpose' => 'Rechnung R-BANK-001',
        'fingerprint' => 'invoice-receipt-test',
        'status' => BankTransaction::STATUS_PENDING,
    ]);

    $this->actingAs($user)->patch(route('bank-imports.transactions.update', $bankTransaction), [
        'source_account_id' => $bankAccount->id,
        'selected_account_id' => $incomeAccount->id,
        'receipt_kind' => 'system_invoice',
        'invoice_id' => $invoice->id,
    ])->assertRedirectContains('#bank-transaction-' . $bankTransaction->id);

    $bankTransaction = BankTransaction::withoutGlobalScopes()->find($bankTransaction->id);

    expect($bankTransaction->receipt_kind)->toBe('system_invoice');
    expect($bankTransaction->receipt_meta['invoice_id'])->toBe($invoice->id);

    $this->actingAs($user)->post(route('bank-imports.transactions.book', $bankTransaction))->assertRedirect();

    $transaction = Transaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($transaction->invoice_id)->toBe($invoice->id);
    expect($transaction->receipt_kind)->toBe('system_invoice');
    expect($transaction->receipt_meta['invoice_number'])->toBe('R-BANK-001');
});
