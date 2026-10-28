<?php

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\PublicForm;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('child exemption respects inclusive age and never makes businesses free', function () {
    $event = new Event(['price_per_person' => 20, 'member_pricing_enabled' => true,
        'member_price_per_person' => 10, 'children_free' => true, 'child_max_age' => 12,
        'organization_bookings_free' => true]);
    foreach ([0, 12] as $age) {
        expect($event->priceForPublicBookingParticipant(['age_at_event' => $age], 'person'))->toBe(0.0);
    }
    foreach ([null, '', 13, -1] as $age) {
        expect($event->priceForPublicBookingParticipant(['age_at_event' => $age], 'person'))->toBe(20.0);
    }
    expect($event->priceForPublicBookingParticipant(['participant_type' => 'member', 'age_at_event' => 12], 'person'))->toBe(0.0)
        ->and($event->priceForPublicBookingParticipant(['organization_booking_type' => 'club'], 'organization'))->toBe(0.0)
        ->and($event->priceForPublicBookingParticipant(['organization_booking_type' => 'business', 'age_at_event' => 0], 'organization'))->toBe(20.0);
    $event->children_free = false;
    expect($event->priceForPublicBookingParticipant(['age_at_event' => 12], 'person'))->toBe(20.0);
});

test('mixed booking charges adults only and retains children in capacity and audit data', function (bool $allChildren) {
    Mail::fake();
    $tenant = Tenant::create(['name' => 'Kinderverein', 'slug' => 'kinderverein', 'email' => 'verein@example.test']);
    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'title' => 'Familienfest', 'start' => now()->addWeek(), 'end' => now()->addWeek()->addHour(),
        'booking_enabled' => true, 'price_per_person' => 20, 'member_pricing_enabled' => false,
        'children_free' => true, 'child_max_age' => 12, 'max_participants_per_booking' => 5, 'max_participants_total' => 5,
    ]);
    $form = PublicForm::create(['tenant_id' => $tenant->id, 'event_id' => $event->id, 'title' => 'Anmeldung',
        'slug' => 'familienfest', 'form_type' => 'event', 'is_active' => true]);
    foreach (['first_name', 'last_name', 'email'] as $slug) {
        $form->fields()->create(['slug' => $slug, 'label' => $slug, 'field_type' => 'text', 'is_required' => true]);
    }
    $this->get(route('forms.public.show', $form->slug))->assertOk()->assertSee('Bis einschließlich 12 Jahre kostenlos');
    $payload = ['fields' => ['first_name' => 'Erika', 'last_name' => 'Test', 'email' => 'erika@example.test',
        'street' => 'Testweg 1', 'zip' => '12345', 'city' => 'Teststadt'],
        'participant_count' => 3, 'use_booker_as_participant' => 1,
        'participants' => [['first_name' => 'Baby', 'last_name' => 'Test', 'age_at_event' => 0],
            ['first_name' => 'Kind', 'last_name' => 'Test', 'age_at_event' => 12]]];
    if ($allChildren) { $payload['booker_age_at_event'] = 12; }
    $invalid = $payload;
    $invalid['participants'][0]['age_at_event'] = -1;
    $this->postJson(route('forms.public.submit', $form->slug), $invalid)->assertUnprocessable();
    expect(EventBooking::count())->toBe(0);
    $this->post(route('forms.public.submit', $form->slug), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $booking = EventBooking::with('participants')->firstOrFail();
    expect((float) $booking->total_amount)->toBe($allChildren ? 0.0 : 20.0)
        ->and($booking->participant_count)->toBe(3)
        ->and($booking->invoice_id === null)->toBe($allChildren)
        ->and($booking->participants[1]->answers['age_at_event'])->toBe(0)
        ->and((float) $booking->participants[2]->price_amount)->toBe(0.0)
        ->and($event->fresh()->remaining_spots)->toBe(2);
})->with([false, true]);

test('event creation validates and stores child pricing settings', function () {
    $this->withoutMiddleware(\App\Http\Middleware\EnsureTenantIsSubscribed::class);
    $tenant = Tenant::create(['name' => 'Kinderverein', 'slug' => 'kinderverein', 'email' => 'verein@example.test']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_EVENT_MANAGER]);
    $payload = ['title' => 'Kinderfest', 'start' => now()->addWeek()->toDateTimeString(),
        'end' => now()->addWeek()->addHour()->toDateTimeString(), 'is_public' => 1,
        'booking_enabled' => 1, 'children_free' => 1, 'organization_bookings_free' => 1, 'price_per_person' => 20];
    $this->actingAs($user)->get(route('events.create'))->assertOk()->assertSee('Kinder kostenlos');
    $this->postJson(route('events.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('child_max_age');
    $payload['child_max_age'] = 12;
    $this->post(route('events.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $event = Event::firstOrFail();
    expect($event->children_free)->toBeTrue()->and($event->child_max_age)->toBe(12)->and($event->organization_bookings_free)->toBeTrue();
});
