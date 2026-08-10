<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\OperatorAnnouncement;
use App\Models\OperatorAnnouncementDelivery;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function createAnnouncementTenant(string $name, string $licenseMode = 'gifted'): Tenant
{
    $suffix = Str::lower(Str::random(6));

    return Tenant::create([
        'name' => $name . ' ' . $suffix,
        'slug' => Str::slug($name) . '-' . $suffix,
        'email' => $suffix . '@example.test',
        'city' => 'Demostadt',
        'license_mode' => $licenseMode,
        'license_expires_at' => now()->addMonth(),
    ]);
}

test('operator superadmin can open the announcement editor', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($operator)
        ->get(route('admin.announcements.create'))
        ->assertOk()
        ->assertSee('Update verfassen')
        ->assertSee('Fett')
        ->assertSee('Live-Vorschau')
        ->assertSee('Testmail an mich');
});

test('operator announcements are sent only to selected club admins', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email' => 'operator@example.test',
        'email_verified_at' => now(),
    ]);

    $selectedTenant = createAnnouncementTenant('Ausgewählter Verein');
    $otherTenant = createAnnouncementTenant('Anderer Verein');

    $selectedAdmin = User::factory()->create([
        'tenant_id' => $selectedTenant->id,
        'role' => User::ROLE_ADMIN,
        'email' => 'admin-selected@example.test',
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'tenant_id' => $selectedTenant->id,
        'role' => User::ROLE_STAFF,
        'email' => 'staff-selected@example.test',
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'role' => User::ROLE_ADMIN,
        'email' => 'admin-other@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($operator)
        ->post(route('admin.announcements.store'), [
            'action' => 'send',
            'subject' => 'Clubano Update',
            'body_markdown' => "Hallo,\n\n**Wichtiges Update**\n\n- Punkt eins",
            'cta_label' => 'Clubano öffnen',
            'cta_url' => 'https://app.clubano.de',
            'recipient_filter' => 'selected',
            'tenant_ids' => [$selectedTenant->id],
        ])
        ->assertRedirect(route('admin.announcements.index'));

    $announcement = OperatorAnnouncement::query()->latest()->firstOrFail();

    expect($announcement->status)->toBe('sent')
        ->and($announcement->body_html)->toContain('<strong>Wichtiges Update</strong>')
        ->and($announcement->deliveries()->count())->toBe(1);

    $delivery = OperatorAnnouncementDelivery::query()->firstOrFail();

    expect($delivery->tenant_id)->toBe($selectedTenant->id)
        ->and($delivery->user_id)->toBe($selectedAdmin->id)
        ->and($delivery->email)->toBe('admin-selected@example.test')
        ->and($delivery->status)->toBe('sent');
});
