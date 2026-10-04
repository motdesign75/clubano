<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\TemplateDispatchLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function createXRechnungTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'XRechnung Verein',
        'slug' => 'xrechnung-verein-' . uniqid(),
        'email' => 'rechnung@example.test',
        'address' => 'Vereinsweg 1',
        'zip' => '31157',
        'city' => 'Sarstedt',
        'iban' => 'DE89370400440532013000',
        'bic' => 'COBADEFFXXX',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $admin];
}

function createXRechnungInvoice(Tenant $tenant, array $overrides = []): Invoice
{
    $invoice = Invoice::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $tenant->id,
        'document_type' => 'invoice',
        'recipient_type' => 'free',
        'recipient_name' => 'Stadt Musterstadt',
        'recipient_email' => 'amt@example.test',
        'recipient_street' => 'Rathausplatz 1',
        'recipient_zip' => '12345',
        'recipient_city' => 'Musterstadt',
        'recipient_country' => 'Deutschland',
        'e_invoice_enabled' => true,
        'e_invoice_format' => 'xrechnung',
        'e_invoice_buyer_reference' => '991-12345-67',
        'e_invoice_order_reference' => 'PO-2026-1',
        'invoice_number' => 'R-XR-' . uniqid(),
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(14)->toDateString(),
        'status' => 'open',
        'discount' => 0,
        'tax_rate' => 0,
        'total' => 119,
    ], $overrides));

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Raumnutzung Sporthalle',
        'details' => 'Zeitraum September 2026',
        'quantity' => 1,
        'unit' => 'Stück',
        'unit_price' => 119,
    ]);

    return $invoice;
}

test('xrechnung export downloads structured xml without changing pdf mail flow', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createXRechnungTenant();
    $invoice = createXRechnungInvoice($tenant);

    $response = $this->actingAs($admin)->get(route('invoices.xrechnung', $invoice));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/xml');

    $xml = $response->getContent();
    expect($xml)->toContain('<cbc:BuyerReference>991-12345-67</cbc:BuyerReference>')
        ->and($xml)->toContain('<cbc:ID>PO-2026-1</cbc:ID>')
        ->and($xml)->toContain('Raumnutzung Sporthalle')
        ->and($xml)->toContain('<cbc:PayableAmount currencyID="EUR">119.00</cbc:PayableAmount>');
});

test('normal invoice mail still sends the pdf attachment only', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    [$tenant, $admin] = createXRechnungTenant();
    $invoice = createXRechnungInvoice($tenant);

    $this->actingAs($admin)
        ->post(route('invoices.send', $invoice))
        ->assertRedirect(route('invoices.show', $invoice));

    $log = TemplateDispatchLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'invoice_sent')
        ->firstOrFail();

    expect($log->meta['invoice_id'])->toBe($invoice->id)
        ->and($log->meta['document_type'])->toBe('invoice');
});

test('xrechnung export explains missing buyer reference', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createXRechnungTenant();
    $invoice = createXRechnungInvoice($tenant, [
        'e_invoice_buyer_reference' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('invoices.xrechnung', $invoice))
        ->assertRedirect(route('invoices.show', $invoice))
        ->assertSessionHas('error');
});
