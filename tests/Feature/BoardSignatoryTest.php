<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TemplateParser;

test('admin can flag board signatories in tenant settings', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Vorstandsverein',
        'slug' => 'vorstandsverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
        'letter_bottom_margin_mm' => 30,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->patch(route('tenant.update'), [
        'name' => $tenant->name,
        'slug' => $tenant->slug,
        'email' => $tenant->email,
        'letter_bottom_margin_mm' => 34,
        'board_signatories' => [
            ['name' => 'Olli Towet', 'role' => '1. Vorsitz', 'enabled' => '1'],
            ['name' => 'Mara Muster', 'role' => 'Kasse', 'enabled' => '1'],
            ['name' => 'Nicht aktiv', 'role' => 'Beisitz', 'enabled' => '0'],
        ],
    ]);

    $response->assertRedirect(route('tenant.show'));

    $tenant->refresh();

    expect($tenant->board_signatories)->toHaveCount(3)
        ->and($tenant->board_signatories[0]['name'])->toBe('Olli Towet')
        ->and($tenant->board_signatories[0]['enabled'])->toBeTrue()
        ->and($tenant->board_signatories[2]['enabled'])->toBeFalse();
});

test('template parser renders active board signatures in one row', function () {
    $tenant = Tenant::create([
        'name' => 'Briefverein',
        'slug' => 'briefverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
        'board_signatories' => [
            ['name' => 'Olli Towet', 'role' => '1. Vorsitz', 'enabled' => true],
            ['name' => 'Mara Muster', 'role' => 'Kasse', 'enabled' => true],
            ['name' => 'Nicht aktiv', 'role' => 'Beisitz', 'enabled' => false],
        ],
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'email' => 'max@example.test',
    ]);

    $html = TemplateParser::parse('<p>Viele Grüße</p>{vorstand_unterschriften}', $member, $tenant);

    expect($html)->toContain('<table')
        ->and($html)->toContain('Olli Towet')
        ->and($html)->toContain('Mara Muster')
        ->and($html)->not->toContain('Nicht aktiv');
});
