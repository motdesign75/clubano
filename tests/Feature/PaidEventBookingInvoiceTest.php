<?php

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\Invoice;
use App\Models\PublicForm;
use App\Models\PublicFormField;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\Mail;

test('paid public event booking creates linked invoice and dispatch log', function () {
    Mail::fake();

    $tenant = Tenant::create([
        'name' => 'Testverein',
        'slug' => 'testverein-paid-event',
        'email' => 'vorstand@example.test',
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerfest',
        'start' => now()->addWeek(),
        'end' => now()->addWeek()->addHours(3),
        'is_public' => true,
        'booking_enabled' => true,
        'price_per_person' => 25,
        'currency' => 'EUR',
        'max_participants_per_booking' => 1,
    ]);

    $form = PublicForm::create([
        'tenant_id' => $tenant->id,
        'event_id' => $event->id,
        'title' => 'Anmeldung Sommerfest',
        'slug' => 'sommerfest-anmeldung',
        'description' => 'Anmeldung',
        'form_type' => 'event',
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    foreach ([
        ['label' => 'Vorname Ansprechpartner', 'slug' => 'first_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
        ['label' => 'Nachname Ansprechpartner', 'slug' => 'last_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
        ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 3],
        ['label' => 'Telefon', 'slug' => 'phone', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 4],
        ['label' => 'Adresse', 'slug' => 'adresse', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 5],
        ['label' => 'PLZ & Ort', 'slug' => 'plz_ort', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 6],
    ] as $field) {
        PublicFormField::create([
            'public_form_id' => $form->id,
            'label' => $field['label'],
            'slug' => $field['slug'],
            'field_type' => $field['field_type'],
            'is_required' => $field['is_required'],
            'sort_order' => $field['sort_order'],
        ]);
    }

    $this->get(route('forms.public.show', $form->slug))
        ->assertOk()
        ->assertDontSee('fields[adresse]', false)
        ->assertDontSee('fields[plz_ort]', false)
        ->assertSee('Strasse und Hausnummer')
        ->assertSee('PLZ')
        ->assertSee('Ort');

    $response = $this->post(route('forms.public.submit', $form->slug), [
        'fields' => [
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'email' => 'max@example.test',
            'phone' => '0123456789',
            'street' => 'Musterstrasse 12',
            'zip' => '12345',
            'city' => 'Musterstadt',
            'country' => 'Deutschland',
        ],
        'participant_count' => 1,
        'use_booker_as_participant' => 1,
    ]);

    $response->assertRedirect();

    $booking = EventBooking::query()->where('event_id', $event->id)->first();

    expect($booking)->not->toBeNull();
    expect($booking->payment_status)->toBe('open');
    expect($booking->invoice_id)->not->toBeNull();

    $invoice = Invoice::query()->find($booking->invoice_id);

    expect($invoice)->not->toBeNull();
    expect($invoice->recipient_name)->toBe('Max Muster');
    expect($invoice->recipient_email)->toBe('max@example.test');
    expect($invoice->recipient_street)->toBe('Musterstrasse 12');
    expect($invoice->status)->toBe('open');
    expect($invoice->items)->toHaveCount(1);

    $dispatchLog = TemplateDispatchLog::query()
        ->where('action', 'event_booking_invoice_sent')
        ->where('recipient_reference', 'max@example.test')
        ->first();

    expect($dispatchLog)->not->toBeNull();
});

test('event booking reuses booker as first participant when multiple participants are booked', function () {
    Mail::fake();

    $tenant = Tenant::create([
        'name' => 'Mehrpersonenverein',
        'slug' => 'mehrpersonenverein-event',
        'email' => 'vorstand-mehr@example.test',
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Workshop',
        'start' => now()->addWeek(),
        'end' => now()->addWeek()->addHours(3),
        'is_public' => true,
        'booking_enabled' => true,
        'price_per_person' => 10,
        'currency' => 'EUR',
        'max_participants_per_booking' => 4,
    ]);

    $form = PublicForm::create([
        'tenant_id' => $tenant->id,
        'event_id' => $event->id,
        'title' => 'Anmeldung Workshop',
        'slug' => 'workshop-anmeldung',
        'description' => 'Anmeldung',
        'form_type' => 'event',
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    foreach ([
        ['label' => 'Vorname Ansprechpartner', 'slug' => 'first_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
        ['label' => 'Nachname Ansprechpartner', 'slug' => 'last_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
        ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 3],
        ['label' => 'Telefon', 'slug' => 'phone', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 4],
    ] as $field) {
        PublicFormField::create([
            'public_form_id' => $form->id,
            'label' => $field['label'],
            'slug' => $field['slug'],
            'field_type' => $field['field_type'],
            'is_required' => $field['is_required'],
            'sort_order' => $field['sort_order'],
        ]);
    }

    $this->get(route('forms.public.show', $form->slug))
        ->assertOk()
        ->assertSee('Ansprechpartner nimmt selbst teil')
        ->assertSee('Du trägst nur noch weitere Personen ein');

    $response = $this->post(route('forms.public.submit', $form->slug), [
        'fields' => [
            'first_name' => 'Anna',
            'last_name' => 'Ansprechpartner',
            'email' => 'anna@example.test',
            'phone' => '0123000000',
            'street' => 'Musterstrasse 12',
            'zip' => '12345',
            'city' => 'Musterstadt',
            'country' => 'Deutschland',
        ],
        'participant_count' => 2,
        'use_booker_as_participant' => 1,
        'participants' => [
            [
                'first_name' => 'Ben',
                'last_name' => 'Begleitung',
                'email' => 'ben@example.test',
                'phone' => '',
            ],
        ],
    ]);

    $response->assertRedirect();

    $booking = EventBooking::query()->where('event_id', $event->id)->with('participants')->first();

    expect($booking)->not->toBeNull();
    expect($booking->participant_count)->toBe(2);
    expect((float) $booking->total_amount)->toBe(20.0);
    expect($booking->participants)->toHaveCount(2);
    expect($booking->participants[0]->full_name)->toBe('Anna Ansprechpartner');
    expect($booking->participants[1]->full_name)->toBe('Ben Begleitung');
});

test('paid public event booking can redeem a voucher and invoices only the remaining amount', function () {
    Mail::fake();

    $tenant = Tenant::create([
        'name' => 'Gutscheinverein',
        'slug' => 'gutscheinverein-event',
        'email' => 'vorstand-gutschein@example.test',
    ]);

    $voucher = Voucher::create([
        'tenant_id' => $tenant->id,
        'code' => 'CLB-2026-TEST79',
        'title' => 'Kursgutschein',
        'original_amount' => 79,
        'remaining_amount' => 79,
        'currency' => 'EUR',
        'issued_at' => now()->toDateString(),
        'status' => Voucher::STATUS_ACTIVE,
    ]);

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Braukurs',
        'start' => now()->addWeek(),
        'end' => now()->addWeek()->addHours(3),
        'is_public' => true,
        'booking_enabled' => true,
        'price_per_person' => 100,
        'currency' => 'EUR',
        'max_participants_per_booking' => 1,
    ]);

    $form = PublicForm::create([
        'tenant_id' => $tenant->id,
        'event_id' => $event->id,
        'title' => 'Anmeldung Braukurs',
        'slug' => 'braukurs-gutschein',
        'description' => 'Anmeldung',
        'form_type' => 'event',
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    foreach ([
        ['label' => 'Vorname Ansprechpartner', 'slug' => 'first_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
        ['label' => 'Nachname Ansprechpartner', 'slug' => 'last_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
        ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 3],
        ['label' => 'Telefon', 'slug' => 'phone', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 4],
    ] as $field) {
        PublicFormField::create([
            'public_form_id' => $form->id,
            'label' => $field['label'],
            'slug' => $field['slug'],
            'field_type' => $field['field_type'],
            'is_required' => $field['is_required'],
            'sort_order' => $field['sort_order'],
        ]);
    }

    $this->get(route('forms.public.show', $form->slug))
        ->assertOk()
        ->assertSee('Gutschein einlösen');

    $response = $this->post(route('forms.public.submit', $form->slug), [
        'fields' => [
            'first_name' => 'Lena',
            'last_name' => 'Hopfen',
            'email' => 'lena@example.test',
            'phone' => '0123000000',
            'street' => 'Musterstrasse 12',
            'zip' => '12345',
            'city' => 'Musterstadt',
            'country' => 'Deutschland',
        ],
        'participant_count' => 1,
        'use_booker_as_participant' => 1,
        'voucher_code' => 'CLB-2026-TEST79',
    ]);

    $response->assertRedirect();

    $booking = EventBooking::query()->where('event_id', $event->id)->firstOrFail();

    expect((float) $booking->gross_amount)->toBe(100.0)
        ->and((float) $booking->voucher_discount_amount)->toBe(79.0)
        ->and((float) $booking->total_amount)->toBe(21.0)
        ->and($booking->payment_status)->toBe('open')
        ->and($booking->invoice_id)->not->toBeNull();

    $invoice = Invoice::query()->with('items')->findOrFail($booking->invoice_id);

    expect($invoice->items)->toHaveCount(2)
        ->and((float) $invoice->getTotal())->toBe(21.0);

    $voucher->refresh();

    expect((float) $voucher->remaining_amount)->toBe(0.0)
        ->and($voucher->status)->toBe(Voucher::STATUS_REDEEMED)
        ->and(VoucherRedemption::query()->where('voucher_id', $voucher->id)->where('event_booking_id', $booking->id)->count())->toBe(1);
});
