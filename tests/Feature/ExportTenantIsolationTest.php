<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Event;
use App\Models\Member;
use App\Models\PublicForm;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('member datenauskunft cannot be exported across tenants', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenantA = Tenant::create([
        'name' => 'Verein A',
        'slug' => 'verein-a-member-export',
        'email' => 'a-member-export@example.test',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Verein B',
        'slug' => 'verein-b-member-export',
        'email' => 'b-member-export@example.test',
    ]);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $memberB = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'first_name' => 'Fremd',
        'last_name' => 'Mitglied',
        'entry_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($userA)->get(route('members.datenauskunft', $memberB));

    $response->assertNotFound();
});

test('public form export cannot be accessed across tenants', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenantA = Tenant::create([
        'name' => 'Verein A',
        'slug' => 'verein-a-form-export',
        'email' => 'a-form-export@example.test',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Verein B',
        'slug' => 'verein-b-form-export',
        'email' => 'b-form-export@example.test',
    ]);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $formB = PublicForm::create([
        'tenant_id' => $tenantB->id,
        'title' => 'Fremdes Formular',
        'slug' => 'fremdes-formular',
        'form_type' => 'general',
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    $response = $this->actingAs($userA)->get(route('forms.export', $formB));

    $response->assertNotFound();
});

test('event exports cannot be accessed across tenants', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenantA = Tenant::create([
        'name' => 'Verein A',
        'slug' => 'verein-a-event-export',
        'email' => 'a-event-export@example.test',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Verein B',
        'slug' => 'verein-b-event-export',
        'email' => 'b-event-export@example.test',
    ]);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $eventB = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Fremdes Event',
        'start' => now()->addWeek(),
        'end' => now()->addWeek()->addHours(2),
        'is_public' => false,
        'booking_enabled' => true,
        'currency' => 'EUR',
        'max_participants_per_booking' => 1,
    ]);

    $participantsResponse = $this->actingAs($userA)->get(route('events.participants.export', $eventB));
    $participantsResponse->assertNotFound();

    $scheduleResponse = $this->actingAs($userA)->get(route('events.schedule.export', $eventB));
    $scheduleResponse->assertNotFound();
});

test('receipt files require finance role and cannot be accessed across tenants', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenantA = Tenant::create([
        'name' => 'Verein A',
        'slug' => 'verein-a-receipt-export',
        'email' => 'a-receipt-export@example.test',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Verein B',
        'slug' => 'verein-b-receipt-export',
        'email' => 'b-receipt-export@example.test',
    ]);

    $viewerA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_VIEWER,
    ]);

    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $sourceAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Fremde Kasse',
        'type' => 'kasse',
    ]);

    $targetAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Fremder Aufwand',
        'type' => 'ausgabe',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'date' => now()->toDateString(),
        'description' => 'Fremder Beleg',
        'amount' => 12.34,
        'account_from_id' => $sourceAccount->id,
        'account_to_id' => $targetAccount->id,
        'receipt_file' => 'receipts/tenant-b/beleg.pdf',
    ]);

    $this->actingAs($viewerA)
        ->get(route('receipts.show', ['path' => 'receipts/tenant-b/beleg.pdf']))
        ->assertForbidden();

    $this->actingAs($adminA)
        ->get(route('receipts.show', ['path' => 'receipts/tenant-b/beleg.pdf']))
        ->assertNotFound();
});

test('private receipt files are served only to the owning finance tenant', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    $tenantA = Tenant::create([
        'name' => 'Verein A Privatbeleg',
        'slug' => 'verein-a-private-receipt',
        'email' => 'a-private-receipt@example.test',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Verein B Privatbeleg',
        'slug' => 'verein-b-private-receipt',
        'email' => 'b-private-receipt@example.test',
    ]);

    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $adminB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $sourceAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Private Kasse',
        'type' => 'kasse',
    ]);

    $targetAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Privater Aufwand',
        'type' => 'ausgabe',
    ]);

    $path = 'receipts/' . $tenantB->id . '/beleg.pdf';
    Storage::disk('local')->put($path, 'PDF');

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'date' => now()->toDateString(),
        'description' => 'Privater Beleg',
        'amount' => 12.34,
        'account_from_id' => $sourceAccount->id,
        'account_to_id' => $targetAccount->id,
        'receipt_file' => 'private:' . $path,
    ]);

    $this->actingAs($adminA)
        ->get(route('receipts.show', ['path' => 'private:' . $path]))
        ->assertNotFound();

    $this->actingAs($adminB)
        ->get(route('receipts.show', ['path' => 'private:' . $path]))
        ->assertOk();
});
