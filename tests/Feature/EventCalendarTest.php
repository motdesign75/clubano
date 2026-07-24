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
        'location' => 'Vereinsheim',
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
        'location' => 'Vereinsheim',
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

test('overlapping events in different resources are not marked as conflicts', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Parallelverein',
        'slug' => 'parallelverein',
        'email' => 'parallel@example.test',
    ]);

    $firstUser = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $secondUser = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $start = now()->addDays(12)->setTime(18, 0);

    $training = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Jugendtraining',
        'location' => 'Sportplatz',
        'responsible_user_id' => $firstUser->id,
        'start' => $start,
        'end' => $start->copy()->addHours(2),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $firstUser->id,
        'updated_by' => $firstUser->id,
    ]);

    $boardMeeting = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Vorstandsrunde',
        'location' => 'Vereinsheim',
        'responsible_user_id' => $secondUser->id,
        'start' => $start->copy()->addMinutes(30),
        'end' => $start->copy()->addHours(2),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $secondUser->id,
        'updated_by' => $secondUser->id,
    ]);

    $response = $this->actingAs($firstUser)->get(route('events.index', [
        'view' => 'day',
        'day' => $start->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertSee('Jugendtraining');
    $response->assertSee('Vorstandsrunde');

    $conflictResponse = $this->actingAs($firstUser)->get(route('events.index', [
        'view' => 'day',
        'day' => $start->format('Y-m-d'),
        'conflicts_only' => 1,
    ]));

    $conflictResponse->assertOk();
    $conflictResponse->assertDontSee(route('events.show', $training), false);
    $conflictResponse->assertDontSee(route('events.show', $boardMeeting), false);
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

test('staff can see and delete calendar events', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Löschverein',
        'slug' => 'loeschverein',
        'email' => 'loeschen@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerfest',
        'start' => now()->addDays(5)->setTime(15, 0),
        'end' => now()->addDays(5)->setTime(20, 0),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $showResponse = $this->actingAs($staff)->get(route('events.show', $event));
    $editResponse = $this->actingAs($staff)->get(route('events.edit', $event));

    $showResponse->assertOk();
    $showResponse->assertSee('Veranstaltung löschen');
    $editResponse->assertOk();
    $editResponse->assertSee('Veranstaltung löschen');

    $deleteResponse = $this->actingAs($staff)->delete(route('events.destroy', $event));

    $deleteResponse->assertRedirect(route('events.index'));
    expect(Event::withoutGlobalScopes()->find($event->id))->toBeNull();
    expect(EventChangeLog::query()->where('action', 'deleted')->where('summary', 'Termin geloescht')->exists())->toBeTrue();
});

test('staff can create real recurring calendar events', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Serienverein',
        'slug' => 'serienverein',
        'email' => 'serie@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $start = now()->addWeek()->next('Monday')->setTime(18, 0);

    $response = $this->actingAs($staff)->post(route('events.store'), [
        'title' => 'Montagstraining',
        'description' => 'Training auf dem Platz',
        'location' => 'Sportplatz',
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
        'is_public' => 0,
        'booking_enabled' => 0,
        'recurrence_enabled' => 1,
        'recurrence_frequency' => 'weekly',
        'recurrence_until' => $start->copy()->addWeeks(2)->toDateString(),
    ]);

    $response->assertRedirect();

    $events = Event::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('title', 'Montagstraining')
        ->orderBy('start')
        ->get();

    expect($events)->toHaveCount(3);
    expect($events->pluck('recurrence_group_id')->unique()->count())->toBe(1);
    expect($events->pluck('start')->map->format('Y-m-d')->all())->toBe([
        $start->toDateString(),
        $start->copy()->addWeek()->toDateString(),
        $start->copy()->addWeeks(2)->toDateString(),
    ]);
});

test('staff can create monthly events on the same weekday position', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Monatsserienverein',
        'slug' => 'monatsserienverein',
        'email' => 'monatsserie@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $start = now()->setDate(2026, 8, 7)->setTime(19, 0);

    $response = $this->actingAs($staff)->post(route('events.store'), [
        'title' => 'Vorstandsstammtisch',
        'description' => 'Jeden ersten Freitag im Monat',
        'location' => 'Vereinsheim',
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
        'is_public' => 0,
        'booking_enabled' => 0,
        'recurrence_enabled' => 1,
        'recurrence_frequency' => 'monthly_nth_weekday',
        'recurrence_until' => '2026-10-31',
    ]);

    $response->assertRedirect();

    $events = Event::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('title', 'Vorstandsstammtisch')
        ->orderBy('start')
        ->get();

    expect($events)->toHaveCount(3);
    expect($events->pluck('start')->map->format('Y-m-d')->all())->toBe([
        '2026-08-07',
        '2026-09-04',
        '2026-10-02',
    ]);
    expect($events->pluck('recurrence_frequency')->unique()->all())->toBe(['monthly_nth_weekday']);
});
