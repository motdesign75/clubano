<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createDocumentReceiptTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'Belegverein ' . Str::random(5),
        'slug' => 'belegverein-' . Str::random(8),
        'email' => 'belege-' . Str::random(5) . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $user];
}

test('documents can be marked as receipts and appear in the receipt inbox', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    [, $user] = createDocumentReceiptTenant();

    $file = UploadedFile::fake()->create('Rechnung Stadtwerke 19.08.2026 129,90 EUR.pdf', 120, 'application/pdf');

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'title' => 'Stadtwerke August',
            'category' => Document::CATEGORY_CLUB,
            'status' => Document::STATUS_ACTIVE,
            'is_booking_receipt' => '1',
            'file' => $file,
        ])
        ->assertRedirect(route('documents.index'));

    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($document->tenant_id)->toBe($user->tenant_id)
        ->and($document->category)->toBe(Document::CATEGORY_FINANCE)
        ->and($document->is_booking_receipt)->toBeTrue()
        ->and($document->receipt_status)->toBe(Document::RECEIPT_READY)
        ->and((float) $document->recognized_amount)->toBe(129.90)
        ->and($document->recognized_vendor)->toContain('Stadtwerke');

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertSee('Beleg-Eingang')
        ->assertSee('Stadtwerke August')
        ->assertSee('Buchung vorbereiten');
});

test('receipt upload mode guides mobile users into the receipt inbox', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    [, $user] = createDocumentReceiptTenant();

    $this->actingAs($user)
        ->get(route('documents.create', ['type' => 'receipt']))
        ->assertOk()
        ->assertSee('Beleg fotografieren')
        ->assertSee('Beleg muss gebucht werden')
        ->assertSee('capture="environment"', false)
        ->assertSee('value="' . Document::CATEGORY_FINANCE . '" selected', false);

    $file = UploadedFile::fake()->image('Kassenbon Getränkemarkt 31.08.2026 24,90 EUR.jpg');

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'title' => 'Kassenbon Getränkemarkt',
            'category' => Document::CATEGORY_FINANCE,
            'status' => Document::STATUS_ACTIVE,
            'is_booking_receipt' => '1',
            'file' => $file,
        ])
        ->assertRedirect(route('documents.index'));

    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($document->is_booking_receipt)->toBeTrue()
        ->and($document->receipt_status)->toBe(Document::RECEIPT_READY)
        ->and($document->category)->toBe(Document::CATEGORY_FINANCE);

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertSee('Noch nicht gebucht')
        ->assertSee('Beleg fotografieren');
});

test('receipt recognition prefers the payable total over tax and change amounts', function () {
    $service = app(\App\Services\ReceiptRecognitionService::class);

    $invoice = UploadedFile::fake()->create('Stadtwerke Netto 100,00 MwSt 19,00 Gesamt 119,00 EUR.pdf', 12, 'application/pdf');
    $cashReceipt = UploadedFile::fake()->image('Getränkemarkt Summe 42,50 Rückgeld 7,50.jpg');

    expect($service->fromUpload($invoice)['recognized_amount'])->toBe(119.00)
        ->and($service->fromUpload($cashReceipt)['recognized_amount'])->toBe(42.50);
});

test('receipt recognition endpoint returns suggestions for the upload form', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $user] = createDocumentReceiptTenant();

    $file = UploadedFile::fake()->create('Baumarkt Gesamt 89,95 EUR 31.08.2026.pdf', 12, 'application/pdf');

    $this->actingAs($user)
        ->postJson(route('documents.receipt.recognize'), [
            'file' => $file,
        ])
        ->assertOk()
        ->assertJsonPath('recognized_amount', '89.95')
        ->assertJsonPath('recognized_date', '2026-08-31')
        ->assertJsonPath('has_amount', true)
        ->assertJsonPath('has_suggestion', true);
});

test('incoming invoices can be tracked with due date and payment details', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    [$tenant, $user] = createDocumentReceiptTenant();

    $file = UploadedFile::fake()->create('Stadtwerke Rechnung RE-998 Gesamt 119,00 EUR fällig 15.09.2026 IBAN DE02120300000000202051.pdf', 120, 'application/pdf');

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'title' => 'Stadtwerke September',
            'category' => Document::CATEGORY_FINANCE,
            'status' => Document::STATUS_ACTIVE,
            'is_booking_receipt' => '1',
            'recognized_amount' => '119.00',
            'recognized_date' => '2026-09-01',
            'recognized_vendor' => 'Stadtwerke',
            'recognized_invoice_number' => 'RE-998',
            'payable_due_date' => '2026-09-15',
            'payable_due_source' => 'explicit',
            'payable_iban' => 'DE02120300000000202051',
            'payable_reference' => 'RE-998',
            'file' => $file,
        ])
        ->assertRedirect(route('documents.index'));

    $document = Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($document->payable_status)->toBe(Document::PAYABLE_OPEN)
        ->and($document->payableRemainingAmount())->toBe(119.00)
        ->and($document->payable_due_date->toDateString())->toBe('2026-09-15')
        ->and($document->payable_iban)->toBe('DE02120300000000202051');

    $this->actingAs($user)
        ->get(route('payables.index'))
        ->assertOk()
        ->assertSee('Eingangsrechnungen im Blick')
        ->assertSee('Stadtwerke')
        ->assertSee('RE-998')
        ->assertSee('15.09.2026');
});

test('receipt recognition does not treat generic photo names as successful amount recognition', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $user] = createDocumentReceiptTenant();

    $file = UploadedFile::fake()->image('image.jpg');

    $this->actingAs($user)
        ->postJson(route('documents.receipt.recognize'), [
            'file' => $file,
        ])
        ->assertOk()
        ->assertJsonPath('recognized_amount', null)
        ->assertJsonPath('recognized_vendor', null)
        ->assertJsonPath('has_amount', false);
});

test('a receipt document can be linked to a new transaction without reuploading the file', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createDocumentReceiptTenant();

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $expense = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '4210',
        'name' => 'Miete',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'is_postable' => true,
    ]);

    $document = Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
        'title' => 'Mietbeleg August',
        'category' => Document::CATEGORY_FINANCE,
        'status' => Document::STATUS_ACTIVE,
        'disk' => 'local',
        'path' => 'documents/test.pdf',
        'original_name' => 'miete.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
        'is_booking_receipt' => true,
        'receipt_status' => Document::RECEIPT_READY,
        'recognized_amount' => 450,
        'recognized_date' => '2026-08-19',
        'recognized_vendor' => 'Vermieter',
    ]);

    $this->actingAs($user)
        ->get(route('documents.receipt.prepare-transaction', $document))
        ->assertRedirect(route('transactions.create', [
            'context' => 'beleg-eingang',
            'receipt_document_id' => $document->id,
            'date' => '2026-08-19',
            'description' => 'Vermieter',
            'amount' => '450.00',
        ]));

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => '2026-08-19',
            'description' => 'Vermieter',
            'amount' => 450,
            'account_from_id' => $bank->id,
            'account_to_id' => $expense->id,
            'tax_area' => 'ideell',
            'status' => 'entwurf',
            'receipt_document_id' => $document->id,
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::withoutGlobalScopes()->firstOrFail();

    expect($transaction->receipt_kind)->toBe('document')
        ->and($transaction->hasAnyReceipt())->toBeTrue()
        ->and($transaction->receipt_meta['document_id'])->toBe($document->id)
        ->and($document->refresh()->receipt_status)->toBe(Document::RECEIPT_BOOKED)
        ->and($document->linked_transaction_id)->toBe($transaction->id);
});

test('booked receipt documents cannot be prepared or reused for another transaction', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createDocumentReceiptTenant();

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
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

    $document = Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
        'title' => 'Papierbeleg',
        'category' => Document::CATEGORY_FINANCE,
        'status' => Document::STATUS_ACTIVE,
        'disk' => 'local',
        'path' => 'documents/papier.pdf',
        'original_name' => 'papier.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
        'is_booking_receipt' => true,
        'receipt_status' => Document::RECEIPT_READY,
        'recognized_amount' => 25,
        'recognized_vendor' => 'Papierladen',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => '2026-08-19',
            'description' => 'Papierladen',
            'amount' => 25,
            'account_from_id' => $bank->id,
            'account_to_id' => $expense->id,
            'tax_area' => 'ideell',
            'status' => 'entwurf',
            'receipt_document_id' => $document->id,
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect($document->refresh()->receipt_status)->toBe(Document::RECEIPT_BOOKED)
        ->and($document->linked_transaction_id)->toBe($transaction->id);

    $this->actingAs($user)
        ->from(route('documents.show', $document))
        ->get(route('documents.receipt.prepare-transaction', $document))
        ->assertRedirect(route('documents.show', $document))
        ->assertSessionHas('error', 'Dieser Beleg ist bereits mit einer Buchung verknüpft und kann nicht noch einmal gebucht werden.');

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'date' => '2026-08-20',
            'description' => 'Papierladen doppelt',
            'amount' => 25,
            'account_from_id' => $bank->id,
            'account_to_id' => $expense->id,
            'tax_area' => 'ideell',
            'status' => 'entwurf',
            'receipt_document_id' => $document->id,
        ])
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHas('error', 'Dieser Beleg ist bereits mit einer Buchung verknüpft. Es wurde keine zweite Buchung erzeugt.');

    expect(Transaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('updating booked receipt data keeps the receipt booked', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createDocumentReceiptTenant();

    $document = Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
        'title' => 'Gebuchter Beleg',
        'category' => Document::CATEGORY_FINANCE,
        'status' => Document::STATUS_ACTIVE,
        'disk' => 'local',
        'path' => 'documents/gebucht.pdf',
        'original_name' => 'gebucht.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
        'is_booking_receipt' => true,
        'receipt_status' => Document::RECEIPT_BOOKED,
        'payable_status' => Document::PAYABLE_PAID,
        'recognized_amount' => 25,
        'recognized_vendor' => 'Alter Name',
    ]);

    $this->actingAs($user)
        ->patch(route('documents.receipt.update', $document), [
            'recognized_amount' => 25,
            'recognized_currency' => 'EUR',
            'recognized_date' => '2026-08-19',
            'recognized_vendor' => 'Korrigierter Name',
        ])
        ->assertRedirect();

    expect($document->refresh()->receipt_status)->toBe(Document::RECEIPT_BOOKED)
        ->and($document->payable_status)->toBe(Document::PAYABLE_PAID)
        ->and($document->recognized_vendor)->toBe('Korrigierter Name');
});
