<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;

test('single member contribution invoice includes admission fee once as draft', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Beitragsverein',
        'slug' => 'beitragsverein',
        'email' => 'beitrag@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Aktiv',
        'amount' => 75,
        'admission_fee' => 25,
        'interval' => 'jährlich',
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Neu',
        'last_name' => 'Mitglied',
        'email' => 'neu@example.test',
        'entry_date' => now()->subDay()->toDateString(),
        'membership_id' => $membership->id,
        'membership_amount' => 75,
        'membership_interval' => 'jährlich',
    ]);

    $response = $this->actingAs($admin)->post(route('members.membership-invoice.store', $member));

    $invoice = Invoice::query()
        ->where('tenant_id', $tenant->id)
        ->where('member_id', $member->id)
        ->with('items')
        ->firstOrFail();

    $response->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->status)->toBe('entwurf');
    expect($invoice->items->pluck('description')->all())->toContain('Mitgliedsbeitrag Aktiv ' . now()->year);
    expect($invoice->items->pluck('description')->all())->toContain('Aufnahmegebühr Aktiv');
    expect((float) $invoice->getTotal())->toBe(100.0);

    $this->actingAs($admin)->post(route('members.membership-invoice.store', $member));

    expect(InvoiceItem::query()
        ->where('description', 'like', 'Aufnahmegebühr%')
        ->whereHas('invoice', fn ($query) => $query->where('tenant_id', $tenant->id)->where('member_id', $member->id))
        ->count())->toBe(1);
});

