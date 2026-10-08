<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

function createFinanceSummaryTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'Finanzuebersicht Verein ' . Str::random(5),
        'slug' => 'finanzuebersicht-' . Str::random(8),
        'email' => 'finanzuebersicht-' . Str::random(5) . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $user];
}

test('finance summary highlights treasurer work queues', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createFinanceSummaryTenant();

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Vereinsbank',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
        'balance_current' => 1250.50,
    ]);

    $invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'document_type' => 'invoice',
        'recipient_type' => 'contact',
        'recipient_name' => 'Stadt Sarstedt',
        'recipient_email' => 'amt@example.test',
        'invoice_number' => 'R-2026-001',
        'invoice_date' => now()->subDays(20)->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'open',
        'tax_rate' => 0,
        'discount' => 0,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Raummiete',
        'quantity' => 1,
        'unit' => 'Stk',
        'unit_price' => 99,
        'tax_rate' => 0,
        'discount' => 0,
    ]);

    Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
        'title' => 'Rechnung Getränkemarkt',
        'category' => Document::CATEGORY_FINANCE,
        'status' => Document::STATUS_ACTIVE,
        'disk' => 'local',
        'path' => 'documents/test/getraenkemarkt.pdf',
        'original_name' => 'getraenkemarkt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1200,
        'is_booking_receipt' => true,
        'receipt_status' => Document::RECEIPT_READY,
        'recognized_amount' => 42.50,
        'recognized_vendor' => 'Getränkemarkt',
    ]);

    $bankImport = BankImport::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'account_id' => $bank->id,
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
        'account_id' => $bank->id,
        'booking_date' => now()->toDateString(),
        'amount' => 99,
        'currency' => 'EUR',
        'direction' => 'credit',
        'counterparty_name' => 'Stadt Sarstedt',
        'purpose' => 'R-2026-001',
        'fingerprint' => 'finance-summary-work-queue',
        'status' => BankTransaction::STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.summary'))
        ->assertOk()
        ->assertSee('Geld ausstehend')
        ->assertSee('Zu bezahlen / zu buchen')
        ->assertSee('Belege prüfen')
        ->assertSee('Bank zuordnen')
        ->assertSee('Bank und Kasse')
        ->assertSee('Stadt Sarstedt')
        ->assertSee('Rechnung Getränkemarkt')
        ->assertSee('Vereinsbank');
});
