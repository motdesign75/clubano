<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Donation;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoVereinSeeder;
use Illuminate\Support\Facades\Hash;

test('demo tenant users see the demo mode banner', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Demo Verein',
        'slug' => 'demo-test',
        'email' => 'demo-test@example.test',
        'license_mode' => 'gifted',
        'is_demo' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Demo-Modus');
    $response->assertSee('Du testest Clubano mit Beispieldaten');
});

test('demo mode blocks dangerous actions', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Demo Sperre',
        'slug' => 'demo-sperre',
        'email' => 'demo-sperre@example.test',
        'license_mode' => 'gifted',
        'is_demo' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $response = $this->actingAs($user)->post(route('mail.send'), [
        'subject' => 'Test',
        'body' => 'Soll im Demo-Modus nicht versendet werden.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');
});

test('demo reset command creates the public demo account', function () {
    $this->artisan('clubano:demo-reset')
        ->expectsOutputToContain('Demo ist bereit.')
        ->assertSuccessful();

    $tenant = Tenant::query()->where('slug', DemoVereinSeeder::TENANT_SLUG)->first();
    $user = User::query()->where('email', DemoVereinSeeder::USER_EMAIL)->first();

    expect($tenant)->not->toBeNull();
    expect($tenant->is_demo)->toBeTrue();
    expect($tenant->license_mode)->toBe('gifted');
    expect($tenant->city)->toBe('Demostadt');
    expect($user)->not->toBeNull();
    expect($user->tenant_id)->toBe($tenant->id);
    expect(Hash::check(DemoVereinSeeder::USER_PASSWORD, $user->password))->toBeTrue();
    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(200);
    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('city', 'Demostadt')->count())->toBe(200);
    expect(Account::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('type', ['bank', 'kasse'])->count())->toBe(2);
    expect(Account::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('type', ['einnahme', 'ausgabe'])->count())->toBe(5);
    expect(Transaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(8);
    expect(Donation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(2);
    expect($tenant->donation_tax_office)->toBe('Finanzamt Demostadt');
    expect($tenant->donation_freistellung_document_id)->not->toBeNull();
    expect($tenant->load('donationFreistellungDocument')->canIssueDonationCertificates())->toBeTrue();
});
