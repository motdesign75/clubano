<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Template;
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
