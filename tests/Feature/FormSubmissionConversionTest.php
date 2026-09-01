<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventBookingParticipant;
use App\Models\Member;
use App\Models\PublicForm;
use App\Models\PublicFormField;
use App\Models\PublicFormSubmission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

function createConversionTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'Formular Verein',
        'slug' => 'formular-verein-' . Str::random(6),
        'email' => 'formular@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $admin];
}

function createConversionForm(Tenant $tenant, string $type = 'membership', ?Event $event = null): PublicForm
{
    $form = PublicForm::create([
        'tenant_id' => $tenant->id,
        'event_id' => $event?->id,
        'title' => 'Anfrage',
        'slug' => 'anfrage-' . Str::random(6),
        'form_type' => $type,
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    foreach ([
        ['label' => 'Vorname', 'slug' => 'first_name', 'field_type' => 'text', 'is_required' => true],
        ['label' => 'Nachname', 'slug' => 'last_name', 'field_type' => 'text', 'is_required' => true],
        ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true],
        ['label' => 'Telefon', 'slug' => 'phone', 'field_type' => 'text', 'is_required' => false],
        ['label' => 'Organisation', 'slug' => 'organization', 'field_type' => 'text', 'is_required' => false],
        ['label' => 'Strasse', 'slug' => 'street', 'field_type' => 'text', 'is_required' => false],
        ['label' => 'PLZ', 'slug' => 'zip', 'field_type' => 'text', 'is_required' => false],
        ['label' => 'Ort', 'slug' => 'city', 'field_type' => 'text', 'is_required' => false],
    ] as $index => $field) {
        PublicFormField::create($field + [
            'public_form_id' => $form->id,
            'sort_order' => $index + 1,
        ]);
    }

    return $form;
}

test('forms can use headings text blocks and dividers without storing answers', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createConversionTenant();
    $form = PublicForm::create([
        'tenant_id' => $tenant->id,
        'title' => 'Strukturiertes Formular',
        'slug' => 'strukturiertes-formular-' . Str::random(6),
        'form_type' => 'general',
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    foreach ([
        ['label' => 'Persönliche Daten', 'slug' => 'personliche_daten', 'field_type' => 'heading', 'help_text' => 'Bitte kurz und sauber ausfüllen.'],
        ['label' => 'Hinweis', 'slug' => 'hinweis', 'field_type' => 'content', 'help_text' => 'Diese Angaben helfen uns bei der Zuordnung.'],
        ['label' => 'Trennlinie', 'slug' => 'trennlinie', 'field_type' => 'divider'],
        ['label' => 'Name', 'slug' => 'name', 'field_type' => 'text', 'is_required' => true],
    ] as $index => $field) {
        PublicFormField::create($field + [
            'public_form_id' => $form->id,
            'sort_order' => $index + 1,
            'is_required' => $field['is_required'] ?? false,
        ]);
    }

    $this->actingAs($admin)
        ->get(route('forms.edit', $form))
        ->assertOk()
        ->assertSee('Überschrift')
        ->assertSee('Textblock')
        ->assertSee('Trennlinie');

    $this->get(route('forms.public.show', $form->slug))
        ->assertOk()
        ->assertSee('Persönliche Daten')
        ->assertSee('Diese Angaben helfen uns bei der Zuordnung.');

    $this->post(route('forms.public.submit', $form->slug), [
        'fields' => [
            'name' => 'Max Muster',
        ],
    ])->assertRedirect();

    $submission = PublicFormSubmission::withoutGlobalScopes()
        ->where('public_form_id', $form->id)
        ->firstOrFail();

    expect($submission->answers)->toBe(['name' => 'Max Muster']);
});

test('membership form submissions stay pending until an admin converts them to members', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createConversionTenant();
    $form = createConversionForm($tenant, 'membership');

    $this->post(route('forms.public.submit', $form->slug), [
        'fields' => [
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'email' => 'mia@example.test',
            'phone' => '05066',
            'street' => 'Markt 1',
            'zip' => '31157',
            'city' => 'Sarstedt',
        ],
    ])->assertRedirect();

    $submission = PublicFormSubmission::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and($submission->member_id)->toBeNull();

    $this->actingAs($admin)
        ->post(route('forms.submissions.convert-member', [$form, $submission]), [
            'confirmed' => '1',
            'entry_date' => '2026-08-16',
        ])
        ->assertRedirect(route('forms.submissions', $form));

    $member = Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($member->first_name)->toBe('Mia')
        ->and($member->last_name)->toBe('Muster')
        ->and($member->email)->toBe('mia@example.test')
        ->and($member->entry_date->toDateString())->toBe('2026-08-16')
        ->and($submission->fresh()->member_id)->toBe($member->id);
});

test('form submissions can be consciously converted to contacts', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createConversionTenant();
    $form = createConversionForm($tenant, 'contact');

    $submission = PublicFormSubmission::create([
        'public_form_id' => $form->id,
        'tenant_id' => $tenant->id,
        'full_name' => 'Max Kontakt',
        'email' => 'max@example.test',
        'phone' => '01234',
        'answers' => [
            'first_name' => 'Max',
            'last_name' => 'Kontakt',
            'email' => 'max@example.test',
            'organization' => 'Kontakt GmbH',
            'city' => 'Demostadt',
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('forms.submissions.convert-contact', [$form, $submission]), [
            'confirmed' => '1',
        ])
        ->assertRedirect(route('forms.submissions', $form));

    $contact = Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($contact->organization)->toBe('Kontakt GmbH')
        ->and($contact->email)->toBe('max@example.test')
        ->and($submission->fresh()->contact_id)->toBe($contact->id);
});

test('member conversion links existing records instead of creating duplicates', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createConversionTenant();
    $form = createConversionForm($tenant, 'membership');

    $existingMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mia',
        'last_name' => 'Muster',
        'email' => 'mia@example.test',
        'entry_date' => '2026-01-01',
    ]);

    $submission = PublicFormSubmission::create([
        'public_form_id' => $form->id,
        'tenant_id' => $tenant->id,
        'full_name' => 'Mia Muster',
        'email' => 'MIA@example.test',
        'answers' => [
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'email' => 'MIA@example.test',
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('forms.submissions.convert-member', [$form, $submission]), [
            'confirmed' => '1',
            'entry_date' => '2026-08-16',
        ])
        ->assertRedirect(route('forms.submissions', $form));

    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and($submission->fresh()->member_id)->toBe($existingMember->id);
});

test('contact conversion links existing records instead of creating duplicates', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createConversionTenant();
    $form = createConversionForm($tenant, 'contact');

    $existingContact = Contact::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'contact_type' => 'organization',
        'organization' => 'Kontakt GmbH',
        'email' => 'kontakt@example.test',
        'city' => 'Demostadt',
    ]);

    $submission = PublicFormSubmission::create([
        'public_form_id' => $form->id,
        'tenant_id' => $tenant->id,
        'full_name' => 'Kontakt GmbH',
        'email' => 'KONTAKT@example.test',
        'answers' => [
            'organization' => 'Kontakt GmbH',
            'email' => 'KONTAKT@example.test',
            'city' => 'Demostadt',
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('forms.submissions.convert-contact', [$form, $submission]), [
            'confirmed' => '1',
        ])
        ->assertRedirect(route('forms.submissions', $form));

    expect(Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and($submission->fresh()->contact_id)->toBe($existingContact->id);
});

test('event related form submissions can be transferred to participant lists after review', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createConversionTenant();
    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerfest',
        'start' => now()->addWeek(),
        'end' => now()->addWeek()->addHours(2),
        'price_per_person' => 15,
        'currency' => 'EUR',
    ]);
    $form = createConversionForm($tenant, 'general', $event);

    $submission = PublicFormSubmission::create([
        'public_form_id' => $form->id,
        'tenant_id' => $tenant->id,
        'event_id' => $event->id,
        'full_name' => 'Tina Teilnehmer',
        'email' => 'tina@example.test',
        'answers' => [
            'first_name' => 'Tina',
            'last_name' => 'Teilnehmer',
            'email' => 'tina@example.test',
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('forms.submissions.convert-participant', [$form, $submission]), [
            'confirmed' => '1',
            'participant_type' => 'guest',
            'payment_required' => '1',
            'price_amount' => '15.00',
            'payment_status' => 'open',
        ])
        ->assertRedirect(route('forms.submissions', $form));

    $booking = EventBooking::withoutGlobalScopes()->where('event_id', $event->id)->firstOrFail();
    $participant = EventBookingParticipant::query()->where('event_booking_id', $booking->id)->firstOrFail();

    expect($booking->participant_count)->toBe(1)
        ->and((float) $booking->total_amount)->toBe(15.0)
        ->and($participant->full_name)->toBe('Tina Teilnehmer')
        ->and($participant->payment_status)->toBe('open')
        ->and($submission->fresh()->event_booking_id)->toBe($booking->id);
});
