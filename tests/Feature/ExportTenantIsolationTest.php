<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Event;
use App\Models\Member;
use App\Models\PublicForm;
use App\Models\Tenant;
use App\Models\User;

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

    $response->assertForbidden();
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

    $response->assertForbidden();
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
    $participantsResponse->assertForbidden();

    $scheduleResponse = $this->actingAs($userA)->get(route('events.schedule.export', $eventB));
    $scheduleResponse->assertForbidden();
});
