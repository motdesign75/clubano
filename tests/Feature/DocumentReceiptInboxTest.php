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
