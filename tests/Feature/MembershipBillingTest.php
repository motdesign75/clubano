<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Member;
use App\Models\MemberCredit;
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

test('membership overview shows members without model and saves next billing date', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Kontrollverein',
        'slug' => 'kontrollverein',
        'email' => 'kontrolle@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Aktiv',
        'amount' => 75,
        'interval' => 'jährlich',
    ]);

    $withoutModel = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ohne',
        'last_name' => 'Modell',
        'entry_date' => now()->subDay()->toDateString(),
    ]);

    $withModel = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mit',
        'last_name' => 'Modell',
        'entry_date' => now()->subDay()->toDateString(),
        'membership_id' => $membership->id,
        'membership_amount' => 75,
        'membership_interval' => 'jährlich',
    ]);

    $this->actingAs($admin)->get(route('memberships.index'))
        ->assertOk()
        ->assertSee('Mitglieder ohne Beitragsmodell')
        ->assertSee('Ohne Modell')
        ->assertSee('Wer ist wann dran?')
        ->assertSee('Mit Modell');

    $this->actingAs($admin)
        ->patch(route('memberships.member-billing.update', $withModel), [
            'next_membership_invoice_on' => now()->addMonth()->toDateString(),
        ])
        ->assertRedirect(route('memberships.index'));

    expect($withModel->refresh()->next_membership_invoice_on?->toDateString())->toBe(now()->addMonth()->toDateString());
});

test('future next billing date blocks membership invoice generation', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Sperrverein',
        'slug' => 'sperrverein',
        'email' => 'sperre@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Aktiv',
        'amount' => 75,
        'interval' => 'jährlich',
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Zukunft',
        'last_name' => 'Termin',
        'entry_date' => now()->subDay()->toDateString(),
        'membership_id' => $membership->id,
        'membership_amount' => 75,
        'membership_interval' => 'jährlich',
        'next_membership_invoice_on' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($admin)->post(route('members.membership-invoice.store', $member))
        ->assertRedirect(route('members.show', $member));

    expect(Invoice::query()->where('tenant_id', $tenant->id)->where('member_id', $member->id)->count())->toBe(0);
});

test('bulk action assigns membership and next billing date to selected members', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Sammelverein',
        'slug' => 'sammelverein',
        'email' => 'sammel@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Aktiv',
        'amount' => 75,
        'interval' => 'jährlich',
    ]);

    $first = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Erstes',
        'last_name' => 'Mitglied',
        'entry_date' => now()->subDay()->toDateString(),
    ]);

    $second = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Zweites',
        'last_name' => 'Mitglied',
        'entry_date' => now()->subDay()->toDateString(),
    ]);

    $nextDate = now()->addWeek()->toDateString();

    $this->actingAs($admin)->post(route('members.bulk-action'), [
        'selected' => [$first->id, $second->id],
        'action' => 'assign_membership',
        'membership_id' => $membership->id,
        'next_membership_invoice_on' => $nextDate,
    ])->assertRedirect(route('members.index'));

    foreach ([$first->refresh(), $second->refresh()] as $member) {
        expect($member->membership_id)->toBe($membership->id);
        expect((float) $member->membership_amount)->toBe(75.0);
        expect($member->membership_interval)->toBe('jährlich');
        expect($member->next_membership_invoice_on?->toDateString())->toBe($nextDate);
    }
});

test('membership center assigns contribution model to selected members', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Zentrale Verein',
        'slug' => 'zentrale-verein',
        'email' => 'zentrale@example.test',
    ]);

    $otherTenant = Tenant::create([
        'name' => 'Fremder Verein',
        'slug' => 'fremder-verein',
        'email' => 'fremd@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Familie',
        'amount' => 120,
        'interval' => 'jährlich',
    ]);

    $foreignMembership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Fremdmodell',
        'amount' => 10,
        'interval' => 'monatlich',
    ]);

    $first = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Anna',
        'last_name' => 'Ohnebeitrag',
        'entry_date' => now()->subDay()->toDateString(),
    ]);

    $second = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ben',
        'last_name' => 'Ohnebeitrag',
        'entry_date' => now()->subDay()->toDateString(),
    ]);

    $nextDate = now()->addMonth()->toDateString();

    $this->actingAs($admin)->get(route('memberships.index'))
        ->assertOk()
        ->assertSee('Alle angezeigten Mitglieder auswählen')
        ->assertSee('Anna Ohnebeitrag')
        ->assertSee('Ben Ohnebeitrag');

    $this->actingAs($admin)->post(route('memberships.member-billing.assign'), [
        'member_ids' => [$first->id, $second->id],
        'membership_id' => $membership->id,
        'next_membership_invoice_on' => $nextDate,
    ])->assertRedirect(route('memberships.index'));

    foreach ([$first->refresh(), $second->refresh()] as $member) {
        expect($member->membership_id)->toBe($membership->id);
        expect((float) $member->membership_amount)->toBe(120.0);
        expect($member->membership_interval)->toBe('jährlich');
        expect($member->next_membership_invoice_on?->toDateString())->toBe($nextDate);
    }

    $this->actingAs($admin)->from(route('memberships.index'))->post(route('memberships.member-billing.assign'), [
        'member_ids' => [$first->id],
        'membership_id' => $foreignMembership->id,
    ])->assertSessionHasErrors('membership_id');
});

test('membership draft can be deleted and recreated with member credit restored', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Entwurfsverein',
        'slug' => 'entwurfsverein',
        'email' => 'entwurf@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Aktiv',
        'amount' => 75,
        'interval' => 'jährlich',
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Korrigier',
        'last_name' => 'Mich',
        'entry_date' => now()->subDay()->toDateString(),
        'membership_id' => $membership->id,
        'membership_amount' => 75,
        'membership_interval' => 'jährlich',
    ]);

    $credit = MemberCredit::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'member_id' => $member->id,
        'created_by' => $admin->id,
        'description' => 'Auslage',
        'amount' => 10,
        'remaining_amount' => 10,
        'credited_at' => now()->toDateString(),
    ]);

    $this->actingAs($admin)->post(route('members.membership-invoice.store', $member));

    $invoice = Invoice::query()
        ->where('tenant_id', $tenant->id)
        ->where('member_id', $member->id)
        ->firstOrFail();

    expect($invoice->status)->toBe('entwurf');
    expect((float) $credit->refresh()->remaining_amount)->toBe(0.0);
    expect($member->refresh()->next_membership_invoice_on?->isFuture())->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('invoices.draft.destroy', $invoice))
        ->assertRedirect(route('invoices.index', ['status' => 'entwurf']));

    expect(Invoice::query()->whereKey($invoice->id)->exists())->toBeFalse();
    expect(InvoiceItem::query()->where('invoice_id', $invoice->id)->exists())->toBeFalse();
    expect((float) $credit->refresh()->remaining_amount)->toBe(10.0);
    expect($member->refresh()->next_membership_invoice_on?->isFuture())->toBeFalse();

    $this->actingAs($admin)->post(route('members.membership-invoice.store', $member));

    expect(Invoice::query()->where('tenant_id', $tenant->id)->where('member_id', $member->id)->count())->toBe(1);
});

test('membership drafts can be deleted in bulk without touching open invoices', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Sammelentwurf',
        'slug' => 'sammelentwurf',
        'email' => 'sammelentwurf@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Aktiv',
        'amount' => 75,
        'interval' => 'jährlich',
    ]);

    $first = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Erster',
        'last_name' => 'Entwurf',
        'entry_date' => now()->subDay()->toDateString(),
        'membership_id' => $membership->id,
        'membership_amount' => 75,
        'membership_interval' => 'jährlich',
    ]);

    $second = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Zweiter',
        'last_name' => 'Entwurf',
        'entry_date' => now()->subDay()->toDateString(),
        'membership_id' => $membership->id,
        'membership_amount' => 75,
        'membership_interval' => 'jährlich',
    ]);

    $this->actingAs($admin)->post(route('members.membership-invoice.store', $first));
    $this->actingAs($admin)->post(route('members.membership-invoice.store', $second));

    $draftIds = Invoice::query()
        ->where('tenant_id', $tenant->id)
        ->whereIn('member_id', [$first->id, $second->id])
        ->pluck('id')
        ->all();

    $openInvoice = Invoice::query()->whereKey($draftIds[0])->firstOrFail();
    $openInvoice->forceFill(['status' => 'open'])->save();

    $this->actingAs($admin)->post(route('invoices.bulk-destroy-drafts'), [
        'invoice_ids' => $draftIds,
    ])->assertRedirect(route('invoices.index', ['status' => 'entwurf']));

    expect(Invoice::query()->whereKey($openInvoice->id)->exists())->toBeTrue();
    expect(Invoice::query()->whereKey($draftIds[1])->exists())->toBeFalse();
});
