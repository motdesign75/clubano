<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;

test('public documents and receipt files can be migrated to private storage', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('public');
    Storage::fake('local');

    $tenant = Tenant::create([
        'name' => 'Privatdateien Verein',
        'slug' => 'privatdateien-verein',
        'email' => 'privatdateien@example.test',
    ]);

    $accountFrom = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bank',
        'type' => 'bank',
    ]);

    $accountTo = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Aufwand',
        'type' => 'ausgabe',
    ]);

    $bankImport = BankImport::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'account_id' => $accountFrom->id,
        'filename' => 'umsatz.csv',
        'format' => 'CSV',
        'status' => 'review',
        'row_count' => 1,
        'imported_count' => 1,
    ]);

    Storage::disk('public')->put('documents/legacy-document.pdf', 'DOC');
    Storage::disk('public')->put('receipts/legacy-transaction.pdf', 'TRX');
    Storage::disk('public')->put('receipts/legacy-bank.pdf', 'BNK');

    $document = Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Alter Vertrag',
        'category' => Document::CATEGORY_CONTRACTS,
        'status' => Document::STATUS_ACTIVE,
        'disk' => 'public',
        'path' => 'documents/legacy-document.pdf',
        'original_name' => 'legacy-document.pdf',
    ]);

    $transaction = Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'date' => now()->toDateString(),
        'description' => 'Alter Beleg',
        'amount' => 12,
        'account_from_id' => $accountFrom->id,
        'account_to_id' => $accountTo->id,
        'receipt_file' => 'receipts/legacy-transaction.pdf',
    ]);

    $bankTransaction = BankTransaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'bank_import_id' => $bankImport->id,
        'account_id' => $accountFrom->id,
        'booking_date' => now()->toDateString(),
        'amount' => -12,
        'direction' => 'debit',
        'fingerprint' => 'legacy-bank-receipt',
        'receipt_file' => 'receipts/legacy-bank.pdf',
    ]);

    $this->artisan('clubano:migrate-private-files')
        ->assertSuccessful();

    expect($document->refresh()->disk)->toBe('local')
        ->and($document->path)->toBe('documents/' . $tenant->id . '/migrated/legacy-document.pdf')
        ->and($transaction->refresh()->receipt_file)->toBe('private:receipts/' . $tenant->id . '/migrated/legacy-transaction.pdf')
        ->and($bankTransaction->refresh()->receipt_file)->toBe('private:receipts/' . $tenant->id . '/migrated/legacy-bank.pdf');

    Storage::disk('local')->assertExists($document->path);
    Storage::disk('local')->assertExists('receipts/' . $tenant->id . '/migrated/legacy-transaction.pdf');
    Storage::disk('local')->assertExists('receipts/' . $tenant->id . '/migrated/legacy-bank.pdf');
});
