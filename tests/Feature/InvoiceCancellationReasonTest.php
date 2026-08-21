<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;

function createOpenInvoiceForCancellation(Tenant $tenant): Invoice
{
    $invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'document_type' => 'invoice',
        'recipient_type' => 'free',
        'recipient_name' => 'Max Muster',
        'recipient_email' => 'max@example.test',
        'invoice_number' => 'R-STORNO-' . uniqid(),
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(14)->toDateString(),
        'status' => 'open',
        'discount' => 0,
        'tax_rate' => 0,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Beitrag',
        'quantity' => 1,
        'unit' => 'Pauschale',
        'unit_price' => 50,
    ]);

    return $invoice;
}

test('invoice cancellation requires a reason', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Stornoverein',
        'slug' => 'stornoverein',
        'email' => 'storno@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $invoice = createOpenInvoiceForCancellation($tenant);

    $this->actingAs($admin)
        ->from(route('invoices.show', $invoice))
        ->patch(route('invoices.status.update', $invoice), [
            'status' => 'storniert',
        ])
        ->assertRedirect(route('invoices.show', $invoice))
        ->assertSessionHasErrors('cancellation_reason');

    expect($invoice->fresh()->status)->toBe('open');
});

test('invoice cancellation stores reason and audit data', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Grundverein',
        'slug' => 'grundverein',
        'email' => 'grund@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $invoice = createOpenInvoiceForCancellation($tenant);

    $this->actingAs($admin)
        ->patch(route('invoices.status.update', $invoice), [
            'status' => 'storniert',
            'cancellation_reason' => 'Doppelt erstellt, neue Rechnung wird vorbereitet.',
        ])
        ->assertRedirect(route('invoices.show', $invoice));

    $invoice->refresh();

    expect($invoice->status)->toBe('storniert');
    expect($invoice->cancellation_reason)->toBe('Doppelt erstellt, neue Rechnung wird vorbereitet.');
    expect($invoice->cancelled_at)->not->toBeNull();
    expect($invoice->cancelled_by)->toBe($admin->id);
});

test('bulk cancellation requires one shared reason for selected invoices', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Sammelstorno',
        'slug' => 'sammelstorno',
        'email' => 'sammel@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $firstInvoice = createOpenInvoiceForCancellation($tenant);
    $secondInvoice = createOpenInvoiceForCancellation($tenant);

    $this->actingAs($admin)
        ->from(route('invoices.index'))
        ->post(route('invoices.bulk-cancel'), [
            'invoice_ids' => [$firstInvoice->id, $secondInvoice->id],
        ])
        ->assertRedirect(route('invoices.index'))
        ->assertSessionHasErrors('cancellation_reason');

    expect($firstInvoice->fresh()->status)->toBe('open');
    expect($secondInvoice->fresh()->status)->toBe('open');

    $this->actingAs($admin)
        ->post(route('invoices.bulk-cancel'), [
            'invoice_ids' => [$firstInvoice->id, $secondInvoice->id],
            'cancellation_reason' => 'Beitragslauf wurde falsch vorbereitet.',
        ])
        ->assertRedirect(route('invoices.index'));

    expect($firstInvoice->fresh()->status)->toBe('storniert');
    expect($secondInvoice->fresh()->status)->toBe('storniert');
    expect($firstInvoice->fresh()->cancellation_reason)->toBe('Beitragslauf wurde falsch vorbereitet.');
    expect($secondInvoice->fresh()->cancellation_reason)->toBe('Beitragslauf wurde falsch vorbereitet.');
});
