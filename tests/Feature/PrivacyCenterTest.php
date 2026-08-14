<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function createPrivacyTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'Datenschutz Verein',
        'slug' => 'datenschutz-verein',
        'email' => 'datenschutz@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $admin];
}

test('admins can open the privacy center', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $admin] = createPrivacyTenant();

    $this->actingAs($admin)
        ->get(route('privacy.index'))
        ->assertOk()
        ->assertSee('Datenschutz')
        ->assertSee('Vollständigen Datenexport erstellen')
        ->assertSee('Supportfreigabe');
});

test('admins can create a tenant data export package', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    [$tenant, $admin] = createPrivacyTenant();

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mia',
        'last_name' => 'Muster',
        'email' => 'mia@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->post(route('privacy.exports.store'))
        ->assertRedirect(route('privacy.index'));

    $export = \App\Models\PrivacyDataExport::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect($export->status)->toBe(\App\Models\PrivacyDataExport::STATUS_READY)
        ->and($export->path)->not->toBeNull();

    Storage::disk('local')->assertExists($export->path);
});

test('admins can grant and revoke temporary support access', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createPrivacyTenant();

    $this->actingAs($admin)
        ->post(route('privacy.support-grants.store'), [
            'scope' => 'metadata',
            'duration' => 2,
            'reason' => 'Hilfe beim Import',
        ])
        ->assertRedirect(route('privacy.index'));

    $grant = SupportAccessGrant::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect($grant->isActive())->toBeTrue()
        ->and($grant->reason)->toBe('Hilfe beim Import');

    $this->actingAs($admin)
        ->patch(route('privacy.support-grants.revoke', $grant))
        ->assertRedirect(route('privacy.index'));

    expect($grant->fresh()->isActive())->toBeFalse();
});
