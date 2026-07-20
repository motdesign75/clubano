<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventChangeLog;
use App\Models\Tenant;
use App\Models\User;

test('viewer can create calendar event and audit log is written', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Kalenderverein',
        'slug' => 'kalenderverein',
        'email' => 'kalender@example.test',
    ]);

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_VIEWER,
    ]);

    $category = EventCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Training',
        'slug' => 'training',
        'color' => '#2563EB',
    ]);

    $response = $this->actingAs($viewer)->post(route('events.store'), [
        'title' => 'Jugendtraining',
        'description' => 'Dienstags auf dem Platz',
        'location' => 'Sportplatz',
        'start' => now()->addWeek()->setTime(18, 0)->toDateTimeString(),
        'end' => now()->addWeek()->setTime(20, 0)->toDateTimeString(),
        'category_id' => $category->id,
        'responsible_user_id' => $viewer->id,
        'is_public' => 0,
    ]);

    $response->assertRedirect();

    $event = Event::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('title', 'Jugendtraining')->first();

    expect($event)->not->toBeNull();
    expect((int) $event->responsible_user_id)->toBe($viewer->id);
    expect((int) $event->created_by)->toBe($viewer->id);
    expect((int) $event->updated_by)->toBe($viewer->id);

    $log = EventChangeLog::query()->where('event_id', $event->id)->where('action', 'created')->first();

    expect($log)->not->toBeNull();
});

test('overlapping events are marked as conflicts in calendar index', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Konfliktverein',
        'slug' => 'konfliktverein',
        'email' => 'konflikt@example.test',
    ]);

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_VIEWER,
    ]);

    $start = now()->addDays(10)->setTime(19, 0);

    Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Vorstand',
        'start' => $start,
        'end' => $start->copy()->addHours(2),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $viewer->id,
        'updated_by' => $viewer->id,
    ]);

    Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Training',
        'start' => $start->copy()->addHour(),
        'end' => $start->copy()->addHours(3),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $viewer->id,
        'updated_by' => $viewer->id,
    ]);

    $response = $this->actingAs($viewer)->get(route('events.index', [
        'month' => $start->format('Y-m'),
    ]));

    $response->assertOk();
    $response->assertSee('Konflikt');
});

test('calendar supports day and year views', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Ansichtsverein',
        'slug' => 'ansichtsverein',
        'email' => 'ansicht@example.test',
    ]);

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_VIEWER,
    ]);

    $start = now()->addDays(5)->setTime(10, 0);

    Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Workshop',
        'start' => $start,
        'end' => $start->copy()->addHours(2),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $viewer->id,
        'updated_by' => $viewer->id,
    ]);

    $dayResponse = $this->actingAs($viewer)->get(route('events.index', [
        'view' => 'day',
        'day' => $start->format('Y-m-d'),
    ]));

    $dayResponse->assertOk();
    $dayResponse->assertSee('Tagesplan');
    $dayResponse->assertSee('Workshop');

    $yearResponse = $this->actingAs($viewer)->get(route('events.index', [
        'view' => 'year',
        'year' => $start->format('Y'),
    ]));

    $yearResponse->assertOk();
    $yearResponse->assertSee('Jahresübersicht');
    $yearResponse->assertSee('Workshop');
});
