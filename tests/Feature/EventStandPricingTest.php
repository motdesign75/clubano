<?php

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\PublicForm;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function standBookingFixture(): array
{
    Mail::fake();
    $tenant = Tenant::create(['name' => 'Marktverein', 'slug' => 'marktverein', 'email' => 'markt@example.test']);
    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'title' => 'Markt', 'start' => now()->addWeek(), 'end' => now()->addWeek()->addHour(),
        'booking_enabled' => true, 'pricing_mode' => 'meter', 'price_per_person' => 10,
        'children_free' => true, 'child_max_age' => 14, 'organization_bookings_free' => true,
        'max_participants_per_booking' => 5, 'max_participants_total' => 10,
    ]);
    $form = PublicForm::create(['tenant_id' => $tenant->id, 'event_id' => $event->id, 'title' => 'Stand buchen',
        'slug' => 'markt', 'form_type' => 'event', 'is_active' => true]);
    foreach (['first_name', 'last_name', 'email'] as $slug) {
        $form->fields()->create(['slug' => $slug, 'label' => $slug, 'field_type' => 'text', 'is_required' => true]);
    }
    $payload = ['fields' => ['first_name' => 'Erika', 'last_name' => 'Test', 'email' => 'erika@example.test',
        'street' => 'Testweg 1', 'zip' => '12345', 'city' => 'Teststadt'],
        'participant_count' => 1, 'use_booker_as_participant' => 1, 'stand_length_meters' => 2.5,
        'booker_age_at_event' => 30];
    return [$tenant, $event, $form, $payload];
}

test('stand booking bills adult half meters and respects club exemption', function (string $mode, bool $freeClub) {
    [$tenant, $event, $form, $payload] = standBookingFixture();
    if ($mode !== 'person') {
        $payload['booking_mode'] = 'organization';
        $payload['organization_booking_type'] = $mode;
        $payload['fields']['organization'] = 'Testorganisation';
    }
    $this->get(route('forms.public.show', $form->slug))->assertOk()->assertSee('Standlänge in Metern');
    $this->post(route('forms.public.submit', $form->slug), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $booking = EventBooking::with(['participants', 'invoice.items'])->firstOrFail();
    expect((float) $booking->total_amount)->toBe($freeClub ? 0.0 : 25.0)
        ->and($booking->participant_count)->toBe(1)
        ->and($booking->participants->first()->answers['stand_length_meters'])->toBe(2.5)
        ->and($event->fresh()->remaining_spots)->toBe(9);
    if ($freeClub) {
        expect($booking->invoice_id)->toBeNull();
    } else {
        $item = $booking->invoice->items->first();
        expect((float) $item->quantity)->toBe(2.5)->and($item->unit)->toBe('lfm')
            ->and((float) $item->unit_price)->toBe(10.0)->and((float) $booking->invoice->getTotal())->toBe(25.0);
    }
    $event->update(['price_per_person' => 99, 'pricing_mode' => 'person']);
    expect((float) $booking->fresh()->total_amount)->toBe($freeClub ? 0.0 : 25.0);
})->with([['person', false], ['club', true], ['business', false], ['organization', false]]);

test('stand lengths must be positive half meter steps and cannot add extra people', function () {
    [$tenant, $event, $form, $payload] = standBookingFixture();
    foreach ([null, 0, -1, 0.25, 2.3, 1000.5, 'abc'] as $length) {
        $this->postJson(route('forms.public.submit', $form->slug), array_replace($payload, ['stand_length_meters' => $length]))
            ->assertUnprocessable()->assertJsonValidationErrors('stand_length_meters');
    }
    $this->postJson(route('forms.public.submit', $form->slug), array_replace($payload, ['participant_count' => 2]))
        ->assertUnprocessable()->assertJsonValidationErrors('participant_count');
    expect(EventBooking::count())->toBe(0);
});

test('stand mode and rate can be saved through event create', function () {
    $this->withoutMiddleware(\App\Http\Middleware\EnsureTenantIsSubscribed::class);
    [$tenant] = standBookingFixture();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($user)->get(route('events.create'))->assertOk()->assertSee('Standplatz pro laufendem Meter');
    $this->post(route('events.store'), ['title' => 'Neuer Markt', 'start' => now()->addWeek()->toDateTimeString(),
        'end' => now()->addWeek()->addHour()->toDateTimeString(), 'is_public' => 1, 'booking_enabled' => 1,
        'pricing_mode' => 'meter', 'price_per_person' => 12.50])->assertSessionHasNoErrors()->assertRedirect();
    expect(Event::where('title', 'Neuer Markt')->firstOrFail()->isStandBooking())->toBeTrue();
});

test('manual stand booking calculates amount from length rather than posted amount', function (bool $child) {
    $this->withoutMiddleware(\App\Http\Middleware\EnsureTenantIsSubscribed::class);
    [$tenant, $event] = standBookingFixture();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_EVENT_MANAGER]);
    $this->actingAs($user)->post(route('events.manual-participants.store', $event), [
        'participant_type' => 'guest', 'guest_mode' => 'person', 'first_name' => 'Erika', 'last_name' => 'Test',
        'stand_length_meters' => 2.5, 'payment_required' => 1, 'price_amount' => 1, 'payment_status' => 'open', 'source' => 'manual',
        'stand_operator_age' => $child ? 14 : 30,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $booking = EventBooking::with('participants')->firstOrFail();
    expect((float) $booking->total_amount)->toBe($child ? 0.0 : 25.0)
        ->and($booking->participants->first()->answers['stand_length_meters'])->toBe(2.5);
})->with([false, true]);

test('whole child stand is free through inclusive cutoff only when enabled', function ($age, bool $enabled, string $mode, bool $free) {
    [$tenant, $event, $form, $payload] = standBookingFixture();
    $event->update(['children_free' => $enabled]);
    $payload['booker_age_at_event'] = $age;
    if ($mode === 'organization') {
        $payload['booking_mode'] = 'organization';
        $payload['organization_booking_type'] = 'business';
        $payload['fields']['organization'] = 'Firma';
    }
    $this->post(route('forms.public.submit', $form->slug), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $booking = EventBooking::with('participants')->firstOrFail();
    expect((float) $booking->total_amount)->toBe($free ? 0.0 : 25.0)
        ->and($booking->invoice_id === null)->toBe($free)
        ->and($booking->participants->first()->payment_required)->toBe(! $free)
        ->and($booking->participants->first()->answers['stand_length_meters'])->toBe(2.5)
        ->and($event->fresh()->remaining_spots)->toBe(9);
})->with([[14, true, 'person', true], [0, true, 'person', true], [15, true, 'person', false],
    [null, true, 'person', false], [14, false, 'person', false], [14, true, 'organization', false]]);

test('verified member meter rate and voucher are applied to stand total', function () {
    [$tenant, $event, $form, $payload] = standBookingFixture();
    \App\Models\Member::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'first_name' => 'Erika',
        'last_name' => 'Test', 'email' => 'erika@example.test']);
    $event->update(['member_pricing_enabled' => true, 'member_price_per_person' => 6]);
    \App\Models\Voucher::create(['tenant_id' => $tenant->id, 'code' => 'STAND5', 'title' => 'Gutschein',
        'original_amount' => 5, 'remaining_amount' => 5, 'currency' => 'EUR', 'issued_at' => now()->toDateString(),
        'status' => \App\Models\Voucher::STATUS_ACTIVE]);
    $payload['booking_claims_membership'] = 1;
    $payload['voucher_code'] = 'STAND5';
    $this->post(route('forms.public.submit', $form->slug), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $booking = EventBooking::with('invoice.items')->firstOrFail();
    expect((float) $booking->gross_amount)->toBe(15.0)->and((float) $booking->total_amount)->toBe(10.0)
        ->and((float) $booking->invoice->getTotal())->toBe(10.0);
});

test('club stand exemption can be disabled and half cent totals round consistently', function () {
    [$tenant, $event, $form, $payload] = standBookingFixture();
    $event->update(['organization_bookings_free' => false, 'price_per_person' => 9.99]);
    $payload['booking_mode'] = 'organization';
    $payload['organization_booking_type'] = 'club';
    $payload['fields']['organization'] = 'Verein';
    $this->post(route('forms.public.submit', $form->slug), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $booking = EventBooking::with('invoice.items')->firstOrFail();
    expect((float) $booking->total_amount)->toBe(24.98)
        ->and(round((float) $booking->invoice->getTotal(), 2))->toBe(24.98);
});
