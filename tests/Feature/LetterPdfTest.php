<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Contact;
use App\Models\Member;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\User;

test('letter pdfs are generated with printable window envelope layout', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Briefverein e.V.',
        'slug' => 'briefverein',
        'email' => 'post@example.test',
        'address' => 'Musterstrasse 12',
        'zip' => '12345',
        'city' => 'Musterstadt',
        'phone' => '01234 5678',
        'letter_bottom_margin_mm' => 45,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $template = Template::create([
        'tenant_id' => $tenant->id,
        'name' => 'Einladung',
        'subject' => 'Einladung zur Mitgliederversammlung',
        'body' => '<p>Sehr geehrte Damen und Herren,</p><p>wir laden Sie herzlich ein.</p>',
        'type' => Template::TYPE_LETTER,
    ]);

    $response = $this->actingAs($user)->post(route('letters.generate'), [
        'template_id' => $template->id,
        'recipient_type' => 'free',
        'free_name' => 'Max Mustermann',
        'free_organization' => 'Muster GmbH',
        'free_street' => 'Empfaengerweg 4',
        'free_zip' => '54321',
        'free_city' => 'Beispielstadt',
        'free_country' => 'DE',
    ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF');
    expect($response->getContent())->not->toContain('>DE<');
});

test('letter pdf address window uses full envelope window dimensions', function () {
    $html = view('letters.pdf', [
        'tenant' => (object) [
            'city' => 'Musterstadt',
            'email' => 'post@example.test',
            'phone' => '01234 5678',
        ],
        'template' => (object) ['subject' => 'Einladung'],
        'letter' => [
            'address_lines' => ['Muster GmbH', 'Max Mustermann', 'Empfaengerweg 4', '54321 Beispielstadt'],
            'body' => '<p>Hallo</p>',
        ],
        'senderLine' => 'Briefverein e.V. · Musterstrasse 12 · 12345 Musterstadt',
        'bottomMargin' => 45,
        'letterheadImagePath' => null,
        'showLetterheadImage' => false,
    ])->render();

    expect($html)->toContain('.address-cell { width: 90mm; }')
        ->and($html)->toContain('width: 90mm;')
        ->and($html)->toContain('font-size: 12pt;')
        ->and($html)->toContain('line-height: 5mm;');
});

test('letter address lines omit country codes for every recipient type', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Briefverein e.V.',
        'slug' => 'briefverein-adressen',
        'email' => 'post@example.test',
        'license_mode' => 'gifted',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $template = Template::create([
        'tenant_id' => $tenant->id,
        'name' => 'Einladung',
        'subject' => 'Einladung',
        'body' => '<p>Hallo</p>',
        'type' => Template::TYPE_LETTER,
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Max',
        'last_name' => 'Mitglied',
        'street' => 'Mitgliederweg 1',
        'zip' => '12345',
        'city' => 'Musterstadt',
        'country' => 'AT',
    ]);

    $contact = Contact::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'organization' => 'Kontakt GmbH',
        'first_name' => 'Klara',
        'last_name' => 'Kontakt',
        'street' => 'Kontaktweg 2',
        'zip' => '54321',
        'city' => 'Kontaktstadt',
        'country' => 'CH',
    ]);

    $this->actingAs($user)->post(route('letters.generate'), [
        'template_id' => $template->id,
        'recipient_type' => 'member',
        'members' => [$member->id],
    ])->assertOk();

    $this->actingAs($user)->post(route('letters.generate'), [
        'template_id' => $template->id,
        'recipient_type' => 'contact',
        'contacts' => [$contact->id],
    ])->assertOk();

    $this->actingAs($user)->post(route('letters.generate'), [
        'template_id' => $template->id,
        'recipient_type' => 'free',
        'free_name' => 'Freie Adresse',
        'free_street' => 'Freiweg 3',
        'free_zip' => '99999',
        'free_city' => 'Freistadt',
        'free_country' => 'DE',
    ])->assertOk();

    $references = TemplateDispatchLog::query()
        ->where('tenant_id', $tenant->id)
        ->orderBy('id')
        ->pluck('recipient_reference')
        ->all();

    expect($references)->toHaveCount(3)
        ->and(implode(' | ', $references))->not->toContain('AT')
        ->and(implode(' | ', $references))->not->toContain('CH')
        ->and(implode(' | ', $references))->not->toContain('DE');
});
