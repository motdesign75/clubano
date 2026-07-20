<?php

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\Invoice;
use App\Models\PublicForm;
use App\Models\PublicFormField;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
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
