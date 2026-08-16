<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventBookingParticipant;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function createParticipantMailContext(): array
{
    $tenant = Tenant::create([
        'name' => 'Teilnehmer Verein',
        'slug' => 'teilnehmer-verein-' . Str::random(6),
        'email' => 'vorstand@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerfest',
        'start' => now()->addWeek(),
        'end' => now()->addWeek()->addHours(3),
        'location' => 'Vereinsheim',
        'is_public' => true,
        'booking_enabled' => true,
        'currency' => 'EUR',
    ]);

    return [$tenant, $admin, $event];
}

function createEventParticipant(Event $event, string $email, string $status = 'confirmed'): EventBookingParticipant
{
    $booking = EventBooking::create([
        'tenant_id' => $event->tenant_id,
        'event_id' => $event->id,
        'booking_reference' => 'EVT-' . $event->id . '-' . Str::upper(Str::random(6)),
        'booker_name' => 'Max Muster',
        'booker_email' => $email,
        'participant_count' => 1,
        'price_per_person' => 0,
        'total_amount' => 0,
        'currency' => 'EUR',
        'payment_status' => 'not_required',
        'booking_status' => $status,
    ]);

    return $booking->participants()->create([
        'participant_type' => 'guest',
        'position' => 1,
        'first_name' => 'Max',
        'last_name' => 'Muster',
        'email' => $email,
        'payment_required' => false,
        'price_amount' => 0,
        'payment_status' => 'not_required',
        'source' => 'manual',
    ]);
}

test('event participants can be mailed with explicit recipient confirmation', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    [$tenant, $admin, $event] = createParticipantMailContext();
    $first = createEventParticipant($event, 'max@example.test');
    $second = createEventParticipant($event, 'mia@example.test');
    $template = Template::create([
        'tenant_id' => $tenant->id,
        'name' => 'Termininfo',
        'subject' => 'Vorlage Termin',
        'body' => '<p>Hallo {{ teilnehmer_name }}</p>',
        'type' => Template::TYPE_MAIL,
    ]);

    $this->actingAs($admin)
        ->post(route('events.participants.mail.send', $event), [
            'participant_ids' => [$first->id, $second->id],
            'template_id' => $template->id,
            'subject' => 'Wichtige Info',
            'body' => '<h2>Hallo {{ teilnehmer_name }}</h2><p>Wir sehen uns bei <strong>{{ event_titel }}</strong>.</p><script>alert("x")</script>',
            'recipient_count_confirmation' => 2,
            'send_confirmed' => '1',
        ])
        ->assertRedirect(route('events.participants.manage', $event));

    expect(TemplateDispatchLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'event_participant_mail')
        ->count())->toBe(2);

    $dispatchLog = TemplateDispatchLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'event_participant_mail')
        ->firstOrFail();

    expect($dispatchLog->message_excerpt)
        ->toContain('Hallo Max Muster')
        ->toContain('Sommerfest')
        ->not->toContain('alert')
        ->and($dispatchLog->template_id)->toBe($template->id);
});

test('event participant mail form opens and shows safe placeholders', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin, $event] = createParticipantMailContext();
    createEventParticipant($event, 'max@example.test');
    Template::create([
        'tenant_id' => $tenant->id,
        'name' => 'Info vor dem Termin',
        'subject' => 'Bitte beachten',
        'body' => '<p>Bitte beachten.</p>',
        'type' => Template::TYPE_MAIL,
    ]);

    $this->actingAs($admin)
        ->get(route('events.participants.mail.form', $event))
        ->assertOk()
        ->assertSee('Teilnehmermail')
        ->assertSee('Vorlage nutzen')
        ->assertSee('Info vor dem Termin')
        ->assertSee('Vorlage übernehmen')
        ->assertSee('tinymce.min.js')
        ->assertSee('participant-placeholder')
        ->assertSee('&#123;&#123; teilnehmer_name &#125;&#125;', false)
        ->assertSee('max@example.test');
});

test('participant mail stops when the confirmed recipient count is wrong', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    [$tenant, $admin, $event] = createParticipantMailContext();
    $participant = createEventParticipant($event, 'max@example.test');

    $this->actingAs($admin)
        ->from(route('events.participants.mail.form', $event))
        ->post(route('events.participants.mail.send', $event), [
            'participant_ids' => [$participant->id],
            'subject' => 'Wichtige Info',
            'body' => 'Hallo',
            'recipient_count_confirmation' => 2,
            'send_confirmed' => '1',
        ])
        ->assertRedirect(route('events.participants.mail.form', $event))
        ->assertSessionHasErrors('recipient_count_confirmation');

    expect(TemplateDispatchLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'event_participant_mail')
        ->count())->toBe(0);
});

test('cancelled bookings are not valid participant mail recipients', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    [$tenant, $admin, $event] = createParticipantMailContext();
    $cancelledParticipant = createEventParticipant($event, 'abgesagt@example.test', 'cancelled');

    $this->actingAs($admin)
        ->from(route('events.participants.mail.form', $event))
        ->post(route('events.participants.mail.send', $event), [
            'participant_ids' => [$cancelledParticipant->id],
            'subject' => 'Wichtige Info',
            'body' => 'Hallo',
            'recipient_count_confirmation' => 1,
            'send_confirmed' => '1',
        ])
        ->assertRedirect(route('events.participants.mail.form', $event))
        ->assertSessionHasErrors('participant_ids');

    expect(TemplateDispatchLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'event_participant_mail')
        ->count())->toBe(0);
});
