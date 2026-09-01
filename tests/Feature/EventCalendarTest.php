<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Mail\EventInvitationMail;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventAttendance;
use App\Models\EventCategory;
use App\Models\EventChangeLog;
use App\Models\EventInvitation;
use App\Models\EventShiftAssignment;
use App\Models\Member;
use App\Models\PublicForm;
use App\Models\PublicFormField;
use App\Models\PublicFormSubmission;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

test('event managers can create calendar event and audit log is written', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Kalenderverein',
        'slug' => 'kalenderverein',
        'email' => 'kalender@example.test',
    ]);

    $eventManager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EVENT_MANAGER,
    ]);

    $category = EventCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Training',
        'slug' => 'training',
        'color' => '#2563EB',
    ]);

    $response = $this->actingAs($eventManager)->post(route('events.store'), [
        'title' => 'Jugendtraining',
        'description' => 'Dienstags auf dem Platz',
        'location' => 'Sportplatz',
        'start' => now()->addWeek()->setTime(18, 0)->toDateTimeString(),
        'end' => now()->addWeek()->setTime(20, 0)->toDateTimeString(),
        'category_id' => $category->id,
        'responsible_user_id' => $eventManager->id,
        'is_public' => 0,
    ]);

    $response->assertRedirect();

    $event = Event::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('title', 'Jugendtraining')->first();

    expect($event)->not->toBeNull();
    expect((int) $event->responsible_user_id)->toBe($eventManager->id);
    expect((int) $event->created_by)->toBe($eventManager->id);
    expect((int) $event->updated_by)->toBe($eventManager->id);

    $log = EventChangeLog::query()->where('event_id', $event->id)->where('action', 'created')->first();

    expect($log)->not->toBeNull();
});

test('event managers can add custom registration fields to event bookings', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Anmeldeverein',
        'slug' => 'anmeldeverein',
        'email' => 'events@example.test',
    ]);

    $eventManager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EVENT_MANAGER,
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerkurs',
        'location' => 'Vereinsheim',
        'start' => now()->addWeek()->setTime(18, 0),
        'end' => now()->addWeek()->setTime(20, 0),
        'is_public' => true,
        'booking_enabled' => true,
        'price_per_person' => 0,
        'currency' => 'EUR',
        'max_participants_per_booking' => 2,
        'created_by' => $eventManager->id,
        'updated_by' => $eventManager->id,
    ]);

    $form = PublicForm::create([
        'tenant_id' => $tenant->id,
        'event_id' => $event->id,
        'title' => 'Anmeldung: Sommerkurs',
        'slug' => 'sommerkurs-anmeldung',
        'description' => 'Melde dich an.',
        'form_type' => 'event',
        'success_message' => 'Danke.',
        'is_active' => true,
    ]);

    foreach ([
        ['label' => 'Vorname Ansprechpartner', 'slug' => 'first_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
        ['label' => 'Nachname Ansprechpartner', 'slug' => 'last_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
        ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 3],
        ['label' => 'Telefon', 'slug' => 'phone', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 4],
    ] as $field) {
        PublicFormField::create($field + ['public_form_id' => $form->id]);
    }

    $this->actingAs($eventManager)->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('Anmeldefelder')
        ->assertSee('Neue Zusatzfrage')
        ->assertSee('Organisationen und Vereine');

    $form->refresh();

    $this->actingAs($eventManager)->post(route('events.booking-fields.store', $event), [
        'label' => 'Essenswunsch',
        'field_type' => 'select',
        'options' => "Vegetarisch\nFleisch\nKeine Angabe",
        'is_required' => '1',
    ])->assertRedirect(route('events.edit', $event) . '#anmeldefelder');

    $customField = PublicFormField::query()
        ->where('public_form_id', $form->id)
        ->where('slug', 'essenswunsch')
        ->first();

    expect($customField)->not->toBeNull()
        ->and($customField->options)->toBe('Vegetarisch|Fleisch|Keine Angabe')
        ->and($customField->is_required)->toBeTrue();

    $this->get(route('forms.public.show', $form->slug))
        ->assertOk()
        ->assertSee('Essenswunsch')
        ->assertSee('Vegetarisch');

    $this->post(route('forms.public.submit', $form->slug), [
        'fields' => [
            'first_name' => 'Anna',
            'last_name' => 'Anmeldung',
            'email' => 'anna@example.test',
            'phone' => '012345',
            'essenswunsch' => 'Vegetarisch',
        ],
        'booking_mode' => 'person',
        'participant_count' => 1,
        'use_booker_as_participant' => 1,
    ])->assertRedirect();

    $submission = PublicFormSubmission::query()
        ->where('public_form_id', $form->id)
        ->firstOrFail();

    expect($submission->answers['essenswunsch'])->toBe('Vegetarisch')
        ->and(EventBooking::query()->where('event_id', $event->id)->count())->toBe(1);
});

test('event bookings can be submitted as organization without participant counter', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Vereinsanmeldung',
        'slug' => 'vereinsanmeldung',
        'email' => 'verein@example.test',
    ]);

    $eventManager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EVENT_MANAGER,
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Netzwerktreffen',
        'location' => 'Forum',
        'start' => now()->addWeek()->setTime(19, 0),
        'end' => now()->addWeek()->setTime(21, 0),
        'is_public' => true,
        'booking_enabled' => true,
        'price_per_person' => 25,
        'currency' => 'EUR',
        'max_participants_per_booking' => 10,
        'created_by' => $eventManager->id,
        'updated_by' => $eventManager->id,
    ]);

    $this->actingAs($eventManager)->get(route('events.edit', $event))->assertOk();

    $form = PublicForm::query()
        ->where('event_id', $event->id)
        ->where('form_type', 'event')
        ->firstOrFail();
    $organizationField = PublicFormField::query()
        ->where('public_form_id', $form->id)
        ->where('slug', 'organization')
        ->firstOrFail();

    $this->actingAs($eventManager)->put(route('events.booking-fields.update', [$event, $organizationField]), [
        'label' => 'Unternehmen, Organisation oder Verein',
        'slug' => 'organization',
        'field_type' => 'text',
        'placeholder' => 'z. B. Musterverein e.V.',
        'help_text' => 'Optional: Wenn nicht eine einzelne Person, sondern eine Organisation angemeldet wird.',
        'is_required' => '1',
    ])->assertRedirect(route('events.edit', $event) . '#anmeldefelder');

    $this->get(route('forms.public.show', $form->slug))
        ->assertOk()
        ->assertSee('Organisation oder Verein anmelden')
        ->assertSee('Unternehmen, Organisation oder Verein');

    $this->post(route('forms.public.submit', $form->slug), [
        'booking_mode' => 'organization',
        'fields' => [
            'organization' => 'Musterverein e.V.',
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'email' => 'max@musterverein.test',
            'phone' => '012345',
            'street' => 'Musterweg 1',
            'zip' => '12345',
            'city' => 'Musterstadt',
            'country' => 'Deutschland',
        ],
    ])->assertRedirect();

    $submission = PublicFormSubmission::query()
        ->where('public_form_id', $form->id)
        ->firstOrFail();
    $booking = EventBooking::query()
        ->where('event_id', $event->id)
        ->with('participants')
        ->firstOrFail();

    expect($submission->answers['booking_mode'])->toBe('organization')
        ->and($submission->answers['organization'])->toBe('Musterverein e.V.')
        ->and($submission->answers['participant_count'])->toBe(1)
        ->and($booking->booker_name)->toBe('Musterverein e.V.')
        ->and($booking->participant_count)->toBe(1)
        ->and((float) $booking->gross_amount)->toBe(25.0)
        ->and($booking->participants)->toHaveCount(1)
        ->and($booking->participants->first()->organization_name)->toBe('Musterverein e.V.');
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

test('staff can manually add event participants from members contacts and guests with payment state', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Nachpflegeverein',
        'slug' => 'nachpflegeverein',
        'email' => 'nachpflege@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerfest',
        'location' => 'Vereinsheim',
        'start' => now()->addDays(10)->setTime(18, 0),
        'end' => now()->addDays(10)->setTime(23, 0),
        'is_public' => false,
        'booking_enabled' => false,
        'price_per_person' => 25,
        'currency' => 'EUR',
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Anna',
        'last_name' => 'Mitglied',
        'email' => 'anna@example.test',
        'mobile' => '0171000000',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $secondMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ben',
        'last_name' => 'Mitglied',
        'email' => 'ben@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $contact = Contact::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'organization' => 'Sponsor GmbH',
        'first_name' => 'Clara',
        'last_name' => 'Kontakt',
        'email' => 'clara@example.test',
        'is_active' => true,
    ]);

    $secondContact = Contact::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'organization' => 'Presse Demo',
        'first_name' => 'Petra',
        'last_name' => 'Kontakt',
        'email' => 'petra@example.test',
        'is_active' => true,
    ]);

    $this->actingAs($staff)->post(route('events.manual-participants.store', $event), [
        'participant_type' => 'member',
        'member_ids' => [$member->id, $secondMember->id],
        'payment_required' => '1',
        'price_amount' => '25.00',
        'payment_status' => 'open',
        'source' => 'phone',
    ])->assertRedirect(route('events.participants.manage', $event));

    $this->actingAs($staff)->post(route('events.manual-participants.store', $event), [
        'participant_type' => 'contact',
        'contact_ids' => [$contact->id, $secondContact->id],
        'payment_status' => 'not_required',
        'payment_reason' => 'Sponsor',
        'source' => 'manual',
    ])->assertRedirect(route('events.participants.manage', $event));

    $this->actingAs($staff)->post(route('events.manual-participants.store', $event), [
        'participant_type' => 'guest',
        'guest_mode' => 'person',
        'first_name' => 'Gunnar',
        'last_name' => 'Gast',
        'email' => 'gunnar@example.test',
        'payment_required' => '1',
        'price_amount' => '10.00',
        'payment_status' => 'paid',
        'source' => 'abendkasse',
    ])->assertRedirect(route('events.participants.manage', $event));

    $this->actingAs($staff)->post(route('events.manual-participants.store', $event), [
        'participant_type' => 'guest',
        'guest_mode' => 'organization',
        'organization_name' => 'Gastverein Demostadt',
        'payment_status' => 'not_required',
        'source' => 'manual',
    ])->assertRedirect(route('events.participants.manage', $event));

    $bookings = EventBooking::query()->where('event_id', $event->id)->with('participants')->get();

    expect($bookings)->toHaveCount(4);
    expect($bookings->sum('total_amount'))->toBe(60.0);
    expect($bookings->flatMap->participants)->toHaveCount(6);
    expect($bookings->flatMap->participants->pluck('participant_type')->sort()->values()->all())->toBe(['contact', 'contact', 'guest', 'guest', 'member', 'member']);
    expect($bookings->flatMap->participants->firstWhere('participant_type', 'contact')->payment_status)->toBe('not_required');
    expect($bookings->flatMap->participants->firstWhere('email', 'gunnar@example.test')->payment_status)->toBe('paid');
    expect($bookings->flatMap->participants->firstWhere('organization_name', 'Gastverein Demostadt')->display_name)->toBe('Gastverein Demostadt');

    $guestParticipant = $bookings->flatMap->participants->firstWhere('email', 'gunnar@example.test');
    $this->actingAs($staff)->patch(route('events.participants.update', [$event, $guestParticipant->booking, $guestParticipant]), [
        'first_name' => 'Gunnar',
        'last_name' => 'Gast',
        'organization_name' => null,
        'email' => 'gunnar@example.test',
        'phone' => null,
        'payment_status' => 'paid',
        'payment_reason' => 'Ehrengast',
        'source' => 'abendkasse',
        'note' => 'Nachträglich kostenfrei gestellt',
    ])->assertRedirect(route('events.participants.manage', $event));

    $guestParticipant->refresh();
    expect($guestParticipant->payment_required)->toBeFalse();
    expect((float) $guestParticipant->price_amount)->toBe(0.0);
    expect($guestParticipant->payment_status)->toBe('not_required');
    expect($guestParticipant->payment_reason)->toBe('Ehrengast');
    expect($guestParticipant->note)->toBe('Nachträglich kostenfrei gestellt');
    expect((float) $guestParticipant->booking->fresh()->total_amount)->toBe(0.0);
    expect((float) EventBooking::query()->where('event_id', $event->id)->sum('total_amount'))->toBe(50.0);

    $memberParticipantIds = EventBooking::query()
        ->where('event_id', $event->id)
        ->with('participants')
        ->get()
        ->flatMap->participants
        ->where('participant_type', 'member')
        ->pluck('id')
        ->all();

    $this->actingAs($staff)->patch(route('events.participants.mark-free', $event), [
        'participant_ids' => $memberParticipantIds,
        'payment_reason' => 'Helfereinsatz',
    ])->assertRedirect(route('events.participants.manage', $event));

    $freeMembers = EventBooking::query()
        ->where('event_id', $event->id)
        ->with('participants')
        ->get()
        ->flatMap->participants
        ->whereIn('id', $memberParticipantIds);

    expect($freeMembers)->toHaveCount(2);
    expect($freeMembers->every(fn ($participant) => $participant->payment_required === false))->toBeTrue();
    expect($freeMembers->every(fn ($participant) => (float) $participant->price_amount === 0.0))->toBeTrue();
    expect($freeMembers->every(fn ($participant) => $participant->payment_status === 'not_required'))->toBeTrue();
    expect($freeMembers->every(fn ($participant) => $participant->payment_reason === 'Helfereinsatz'))->toBeTrue();
    expect((float) EventBooking::query()->where('event_id', $event->id)->sum('total_amount'))->toBe(0.0);

    $this->actingAs($staff)->get(route('events.participants.manage', $event))
        ->assertOk()
        ->assertSee('Teilnehmer nachtragen')
        ->assertSee('Sponsor GmbH - Clara Kontakt')
        ->assertSee('Teilnehmer bearbeiten')
        ->assertSee('Ausgewählte kostenfrei setzen')
        ->assertSee('PDF herunterladen')
        ->assertSee('Firma / Organisation')
        ->assertSee('Nachträglich kostenfrei gestellt');

    $this->actingAs($staff)->get(route('events.participants.print', $event))
        ->assertOk()
        ->assertSee('Teilnehmerliste')
        ->assertSee('Ben Mitglied')
        ->assertSee('Petra Kontakt')
        ->assertSee('Presse Demo')
        ->assertSee('Gastverein Demostadt');

    $this->actingAs($staff)->get(route('events.participants.print', [$event, 'display' => 'organization']))
        ->assertOk()
        ->assertSee('Anzeige: Firma / Organisation')
        ->assertSee('Presse Demo')
        ->assertSee('Petra Kontakt');

    $pdfResponse = $this->actingAs($staff)->get(route('events.participants.pdf', [$event, 'display' => 'organization']));
    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('content-type'))->toContain('application/pdf');
    expect($pdfResponse->headers->get('content-disposition'))->toContain('attachment;');
    expect($pdfResponse->getContent())->toStartWith('%PDF');
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

test('staff can choose events for a printable poster overview', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Aushangverein',
        'slug' => 'aushangverein',
        'email' => 'aushang@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $firstEvent = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerfest',
        'description' => 'In gem&uuml;tlicher Atmosph&auml;re feiern wir gemeinsam.',
        'location' => 'Vereinsheim',
        'start' => now()->addDays(10)->setTime(15, 0),
        'end' => now()->addDays(10)->setTime(22, 0),
        'is_public' => true,
        'booking_enabled' => false,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $secondEvent = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Internes Planungstreffen',
        'location' => 'Geschäftsstelle',
        'start' => now()->addDays(12)->setTime(19, 0),
        'end' => now()->addDays(12)->setTime(20, 0),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $selectionResponse = $this->actingAs($staff)->get(route('events.poster'));

    $selectionResponse->assertOk();
    $selectionResponse->assertSee('Terminaushang');
    $selectionResponse->assertSee('Sommerfest');
    $selectionResponse->assertSee('Internes Planungstreffen');

    $printResponse = $this->actingAs($staff)->post(route('events.poster.print'), [
        'headline' => 'Termine im Vereinsheim',
        'note' => 'Bitte vormerken.',
        'event_ids' => [$firstEvent->id],
    ]);

    $printResponse->assertOk();
    $printResponse->assertSee('Termine im Vereinsheim');
    $printResponse->assertSee('Bitte vormerken.');
    $printResponse->assertSee('Sommerfest');
    $printResponse->assertSee('gemütlicher Atmosphäre');
    $printResponse->assertDontSee('gem&uuml;tlicher', false);
    $printResponse->assertDontSee('Internes Planungstreffen');

    $pdfResponse = $this->actingAs($staff)->post(route('events.poster.pdf'), [
        'headline' => 'Termine im Vereinsheim',
        'note' => 'Bitte vormerken.',
        'event_ids' => [$firstEvent->id],
    ]);

    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('content-type'))->toContain('application/pdf');
    expect($pdfResponse->getContent())->toStartWith('%PDF');

    expect($secondEvent->exists)->toBeTrue();
});

test('staff can record attendance and count selected hours toward member duty hours', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Pflichtstundenverein',
        'slug' => 'pflichtstundenverein',
        'email' => 'pflicht@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $countedMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Anna',
        'last_name' => 'Arbeit',
        'email' => 'anna@example.test',
        'entry_date' => now()->subYear()->toDateString(),
        'required_service_hours' => 10,
    ]);

    $presentOnlyMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ben',
        'last_name' => 'Besuch',
        'email' => 'ben@example.test',
        'entry_date' => now()->subYear()->toDateString(),
        'required_service_hours' => 8,
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Vereinsarbeit',
        'location' => 'Vereinsheim',
        'start' => now()->subDay()->setTime(10, 0),
        'end' => now()->subDay()->setTime(13, 0),
        'is_public' => false,
        'booking_enabled' => false,
        'attendance_enabled' => true,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $showResponse = $this->actingAs($staff)->get(route('events.show', $event));

    $showResponse->assertOk();
    $showResponse->assertSee('Wer war da?');
    $showResponse->assertSee('Pflichtstunden');

    $response = $this->actingAs($staff)->put(route('events.attendance.update', $event), [
        'attendances' => [
            [
                'member_id' => $countedMember->id,
                'attended' => 1,
                'hours' => 3,
                'counts_toward_required_hours' => 1,
            ],
            [
                'member_id' => $presentOnlyMember->id,
                'attended' => 1,
                'hours' => 3,
                'counts_toward_required_hours' => 0,
            ],
        ],
    ]);

    $response->assertRedirect(route('events.show', $event));

    $countedAttendance = EventAttendance::query()
        ->where('event_id', $event->id)
        ->where('member_id', $countedMember->id)
        ->first();
    $presentOnlyAttendance = EventAttendance::query()
        ->where('event_id', $event->id)
        ->where('member_id', $presentOnlyMember->id)
        ->first();

    expect($countedAttendance)->not->toBeNull();
    expect($countedAttendance->attended)->toBeTrue();
    expect((float) $countedAttendance->hours)->toBe(3.0);
    expect($countedAttendance->counts_toward_required_hours)->toBeTrue();
    expect($presentOnlyAttendance->counts_toward_required_hours)->toBeFalse();

    $memberResponse = $this->actingAs($staff)->get(route('members.show', $countedMember));

    $memberResponse->assertOk();
    $memberResponse->assertSee('10,00 h');
    $memberResponse->assertSee('3,00 h');
    $memberResponse->assertSee('7,00 h');
});

test('staff can evaluate attendances and open duty hours', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Auswertungsverein',
        'slug' => 'auswertungsverein',
        'email' => 'auswertung@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Clara',
        'last_name' => 'Dienst',
        'email' => 'clara@example.test',
        'entry_date' => now()->subYear()->toDateString(),
        'required_service_hours' => 6,
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Arbeitsdienst',
        'location' => 'Vereinsheim',
        'start' => now()->setDate(now()->year, 5, 10)->setTime(9, 0),
        'end' => now()->setDate(now()->year, 5, 10)->setTime(12, 0),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    EventAttendance::query()->create([
        'tenant_id' => $tenant->id,
        'event_id' => $event->id,
        'member_id' => $member->id,
        'attended' => true,
        'hours' => 2.5,
        'counts_toward_required_hours' => true,
        'recorded_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)->get(route('events.attendance.report', [
        'date_from' => now()->startOfYear()->toDateString(),
        'date_to' => now()->endOfYear()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertSee('Anwesenheit auswerten');
    $response->assertSee('Clara Dienst');
    $response->assertSee('Arbeitsdienst');
    $response->assertSee('6,00 h');
    $response->assertSee('2,50 h');
    $response->assertSee('3,50 h');
});

test('event managers can assign multiple members to a shift at once', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Dienstplanverein',
        'slug' => 'dienstplanverein',
        'email' => 'dienstplan@example.test',
    ]);

    $eventManager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EVENT_MANAGER,
    ]);

    $members = collect(['Anna Aufbau', 'Ben Theke', 'Clara Kasse'])->map(function (string $name) use ($tenant) {
        [$firstName, $lastName] = explode(' ', $name, 2);

        return Member::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => Str::slug($name).'@example.test',
            'entry_date' => now()->subYear()->toDateString(),
        ]);
    });

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerfest',
        'location' => 'Vereinsheim',
        'start' => now()->addMonth()->setTime(10, 0),
        'end' => now()->addMonth()->setTime(18, 0),
        'is_public' => false,
        'booking_enabled' => false,
        'created_by' => $eventManager->id,
        'updated_by' => $eventManager->id,
    ]);

    $shift = $event->shifts()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Theke',
        'starts_at' => $event->start,
        'ends_at' => $event->start->copy()->addHours(3),
        'required_people' => 3,
    ]);

    $response = $this->actingAs($eventManager)->post(route('events.shifts.assignments.store', [$event, $shift]), [
        'member_ids' => $members->take(2)->pluck('id')->all(),
        'status' => 'confirmed',
        'notes' => 'Erste Schicht',
    ]);

    $response->assertRedirect(route('events.schedule.manage', $event));
    $response->assertSessionHas('success', '2 Helferzuordnungen wurden gespeichert.');

    expect(EventShiftAssignment::query()->where('event_shift_id', $shift->id)->where('status', 'confirmed')->count())->toBe(2);

    $secondResponse = $this->actingAs($eventManager)->post(route('events.shifts.assignments.store', [$event, $shift]), [
        'member_ids' => $members->pluck('id')->all(),
        'status' => 'confirmed',
    ]);

    $secondResponse->assertRedirect(route('events.schedule.manage', $event));
    $secondResponse->assertSessionHas('success', 'Eine Helferzuordnung wurde gespeichert.');

    expect(EventShiftAssignment::query()->where('event_shift_id', $shift->id)->where('status', 'confirmed')->count())->toBe(3);
});

test('club can define activity profiles and use them on events', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Aktivitätsverein',
        'slug' => 'aktivitaetsverein',
        'email' => 'aktivitaet@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $targetGroup = Tag::create([
        'tenant_id' => $tenant->id,
        'name' => 'Helferteam',
        'color' => '#16A34A',
    ]);

    $categoryResponse = $this->actingAs($staff)->post(route('event-categories.store'), [
        'name' => 'Arbeitseinsatz',
        'slug' => 'arbeitseinsatz',
        'color' => '#16A34A',
        'icon' => 'wrench',
        'default_target_tag_id' => $targetGroup->id,
        'default_visibility' => 'internal',
        'response_required_default' => 1,
        'attendance_enabled_default' => 1,
        'counts_toward_required_hours_default' => 1,
        'reminders_enabled_default' => 1,
    ]);

    $categoryResponse->assertRedirect(route('event-categories.index'));

    $category = EventCategory::withoutGlobalScopes()->where('slug', 'arbeitseinsatz')->firstOrFail();

    expect($category->default_target_tag_id)->toBe($targetGroup->id);
    expect($category->attendance_enabled_default)->toBeTrue();
    expect($category->counts_toward_required_hours_default)->toBeTrue();

    $eventResponse = $this->actingAs($staff)->post(route('events.store'), [
        'title' => 'Vereinsheim vorbereiten',
        'description' => 'Alles für das Sommerfest vorbereiten.',
        'location' => 'Vereinsheim',
        'start' => now()->addWeek()->setTime(9, 0)->toDateTimeString(),
        'end' => now()->addWeek()->setTime(12, 0)->toDateTimeString(),
        'category_id' => $category->id,
        'target_tag_id' => $targetGroup->id,
        'responsible_user_id' => $staff->id,
        'is_public' => 0,
        'booking_enabled' => 0,
        'response_required' => 1,
        'attendance_enabled' => 1,
        'counts_toward_required_hours' => 1,
        'reminders_enabled' => 1,
    ]);

    $event = Event::withoutGlobalScopes()->where('title', 'Vereinsheim vorbereiten')->firstOrFail();

    $eventResponse->assertRedirect(route('events.edit', $event));
    expect($event->target_tag_id)->toBe($targetGroup->id);
    expect($event->response_required)->toBeTrue();
    expect($event->attendance_enabled)->toBeTrue();
    expect($event->counts_toward_required_hours)->toBeTrue();

    $showResponse = $this->actingAs($staff)->get(route('events.show', $event));

    $showResponse->assertOk();
    $showResponse->assertSee('Aktivitätssteuerung');
    $showResponse->assertSee('Helferteam');
    $showResponse->assertSee('Wird vorgeschlagen');
});

test('staff can build invitation list from event target group and manage responses', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Einladungsverein',
        'slug' => 'einladungsverein',
        'email' => 'einladung@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $targetGroup = Tag::create([
        'tenant_id' => $tenant->id,
        'name' => '1. Mannschaft',
        'color' => '#2563EB',
    ]);

    $firstMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mara',
        'last_name' => 'Mitte',
        'email' => 'mara@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);
    $secondMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Nils',
        'last_name' => 'Netz',
        'email' => 'nils@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);
    $outsideMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ole',
        'last_name' => 'Ohne',
        'email' => 'ole@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $firstMember->tags()->attach($targetGroup->id);
    $secondMember->tags()->attach($targetGroup->id);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Training Dienstag',
        'location' => 'Sportplatz',
        'start' => now()->addDays(3)->setTime(18, 0),
        'end' => now()->addDays(3)->setTime(20, 0),
        'target_tag_id' => $targetGroup->id,
        'is_public' => false,
        'booking_enabled' => false,
        'response_required' => true,
        'attendance_enabled' => true,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $syncResponse = $this->actingAs($staff)->post(route('events.invitations.sync', $event));

    $syncResponse->assertRedirect(route('events.show', $event));
    expect(EventInvitation::query()->where('event_id', $event->id)->count())->toBe(2);
    expect(EventInvitation::query()->where('event_id', $event->id)->where('member_id', $outsideMember->id)->exists())->toBeFalse();

    $firstInvitation = EventInvitation::query()
        ->where('event_id', $event->id)
        ->where('member_id', $firstMember->id)
        ->firstOrFail();
    $secondInvitation = EventInvitation::query()
        ->where('event_id', $event->id)
        ->where('member_id', $secondMember->id)
        ->firstOrFail();

    $updateResponse = $this->actingAs($staff)->put(route('events.invitations.update', $event), [
        'invitations' => [
            [
                'id' => $firstInvitation->id,
                'status' => EventInvitation::STATUS_ACCEPTED,
                'note' => 'Ist dabei.',
            ],
            [
                'id' => $secondInvitation->id,
                'status' => EventInvitation::STATUS_DECLINED,
                'note' => 'Verletzt.',
            ],
        ],
    ]);

    $updateResponse->assertRedirect(route('events.show', $event));
    expect($firstInvitation->fresh()->status)->toBe(EventInvitation::STATUS_ACCEPTED);
    expect($firstInvitation->fresh()->note)->toBe('Ist dabei.');
    expect($secondInvitation->fresh()->status)->toBe(EventInvitation::STATUS_DECLINED);

    $showResponse = $this->actingAs($staff)->get(route('events.show', $event));

    $showResponse->assertOk();
    $showResponse->assertSee('Wer soll dabei sein?');
    $showResponse->assertSee('Zu-/Absage-Link');
    $showResponse->assertSee('1. Mannschaft');
    $showResponse->assertSee('Mara Mitte');
    $showResponse->assertSee('Nils Netz');
    $showResponse->assertDontSee('Ole Ohne');

    $publicResponse = $this->get(route('events.invitations.public.show', $firstInvitation->fresh()->response_token));

    $publicResponse->assertOk();
    $publicResponse->assertSee('Training Dienstag');
    $publicResponse->assertSee('Mara Mitte');
    $publicResponse->assertSee('Ich bin dabei');

    $storePublicResponse = $this->post(route('events.invitations.public.store', $firstInvitation->fresh()->response_token), [
        'status' => EventInvitation::STATUS_MAYBE,
        'note' => 'Entscheide ich nach der Arbeit.',
    ]);

    $storePublicResponse->assertRedirect(route('events.invitations.public.show', $firstInvitation->fresh()->response_token));
    expect($firstInvitation->fresh()->status)->toBe(EventInvitation::STATUS_MAYBE);
    expect($firstInvitation->fresh()->note)->toBe('Entscheide ich nach der Arbeit.');
    expect($firstInvitation->fresh()->responded_at)->not->toBeNull();
});

test('staff can send invitation mails with personal response links', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    $tenant = Tenant::create([
        'name' => 'Mailverein',
        'slug' => 'mailverein',
        'email' => 'mailverein@example.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $targetGroup = Tag::create([
        'tenant_id' => $tenant->id,
        'name' => 'Vorstand',
        'color' => '#2563EB',
    ]);

    $memberWithMail = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Eva',
        'last_name' => 'Email',
        'email' => 'eva@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);
    $memberWithoutMail = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Kai',
        'last_name' => 'Keine',
        'email' => null,
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $memberWithMail->tags()->attach($targetGroup->id);
    $memberWithoutMail->tags()->attach($targetGroup->id);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Vorstandsrunde',
        'location' => 'Clubraum',
        'start' => now()->addDays(8)->setTime(19, 0),
        'end' => now()->addDays(8)->setTime(20, 30),
        'target_tag_id' => $targetGroup->id,
        'is_public' => false,
        'booking_enabled' => false,
        'response_required' => true,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)->post(route('events.invitations.mail', $event));

    $response->assertRedirect(route('events.show', $event));
    $response->assertSessionHas('success', '1 Einladung per Mail gesendet. 1 ohne E-Mail-Adresse übersprungen.');

    expect(EventInvitation::query()->where('event_id', $event->id)->count())->toBe(2);
    $invitation = EventInvitation::query()
        ->where('event_id', $event->id)
        ->where('member_id', $memberWithMail->id)
        ->firstOrFail();
    expect($invitation->response_token)->not->toBeNull();

    Mail::assertSent(EventInvitationMail::class, function (EventInvitationMail $mail) use ($memberWithMail, $invitation) {
        return $mail->hasTo($memberWithMail->email)
            && $mail->invitation->is($invitation);
    });
    Mail::assertSentCount(1);
});
