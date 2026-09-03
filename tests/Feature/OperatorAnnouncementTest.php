<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\OperatorAnnouncement;
use App\Models\OperatorAnnouncementDelivery;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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
        ->assertSee('Update gestalten')
        ->assertSee('Bilder werden direkt in Clubano gespeichert')
        ->assertSee('Konkrete Empfänger')
        ->assertSee('Vorschau')
        ->assertSee('Art der Mitteilung')
        ->assertSee('Produktupdates respektieren Abmeldungen')
        ->assertSee('Testmail an')
        ->assertSee('Testmail senden');
});

test('operator can send a test announcement to their own account', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::shouldReceive('send')->once();

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email' => 'operator@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($operator)
        ->post(route('admin.announcements.store'), [
            'action' => 'test',
            'subject' => 'Clubano Update',
            'body_markdown' => '<p>Hallo,</p><p>das ist eine Testmail.</p>',
            'cta_label' => 'Clubano öffnen',
            'cta_url' => 'https://app.clubano.de',
            'test_email' => 'testziel@example.test',
            'category' => OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE,
            'recipient_filter' => 'all_active',
        ])
        ->assertRedirect(route('admin.announcements.index'))
        ->assertSessionHas('success', 'Testmail wurde an dein Betreiberkonto gesendet.');

    $announcement = OperatorAnnouncement::query()->latest()->firstOrFail();

    expect($announcement->status)->toBe('test')
        ->and($announcement->recipient_summary['recipient_count'])->toBe(1);

});

test('operator test announcement keeps umlauts and clickable cta in rendered mail data', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    Mail::shouldReceive('send')
        ->once()
        ->with(
            'admin.announcements.mail',
            Mockery::on(fn (array $data) => str_contains($data['bodyHtml'], 'Die GHG lädt')
                && ! str_contains($data['bodyHtml'], 'lÃ¤dt')
                && ($data['ctaUrl'] ?? null) === 'https://app.clubano.de/dashboard'),
            Mockery::type(Closure::class)
        );

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email' => 'operator@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($operator)
        ->post(route('admin.announcements.store'), [
            'action' => 'test',
            'subject' => 'Clubano Update',
            'body_markdown' => '<p>Die GHG lÃ¤dt zum **Goldenen Oktober** ein.</p>',
            'cta_label' => 'Update öffnen',
            'cta_url' => 'https://app.clubano.de/dashboard',
            'test_email' => 'testziel@example.test',
            'category' => OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE,
            'recipient_filter' => 'all_active',
        ])
        ->assertRedirect(route('admin.announcements.index'))
        ->assertSessionHas('success', 'Testmail wurde an dein Betreiberkonto gesendet.');
});

test('operator test announcement failures return to editor instead of crashing', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP nicht erreichbar'));

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email' => 'operator@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($operator)
        ->from(route('admin.announcements.create'))
        ->post(route('admin.announcements.store'), [
            'action' => 'test',
            'subject' => 'Clubano Update',
            'body_markdown' => '<p>Hallo,</p><p>das ist eine Testmail.</p>',
            'cta_label' => 'Clubano öffnen',
            'cta_url' => 'https://app.clubano.de',
            'test_email' => 'testziel@example.test',
            'category' => OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE,
            'recipient_filter' => 'all_active',
        ])
        ->assertRedirect(route('admin.announcements.create'))
        ->assertSessionHas('error', 'Die Testmail konnte nicht versendet werden. Bitte prüfe die Mail-Einstellungen oder den Inhalt der Nachricht.');

    $announcement = OperatorAnnouncement::query()->latest()->firstOrFail();

    expect($announcement->status)->toBe('failed')
        ->and($announcement->recipient_summary['failed'])->toBe(1)
        ->and($announcement->recipient_summary['error'])->toContain('SMTP nicht erreichbar');
});

test('operator announcements are sent only to explicitly selected recipients', function () {
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
            'body_markdown' => '<p>Hallo,</p><p><strong>Wichtiges Update</strong></p><ul><li>Punkt eins</li></ul>',
            'cta_label' => 'Clubano öffnen',
            'cta_url' => 'https://app.clubano.de',
            'category' => OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE,
            'recipient_filter' => 'selected',
            'recipient_user_ids' => [$selectedAdmin->id],
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
        ->and($delivery->status)->toBe('sent')
        ->and($delivery->tracking_token)->not->toBeNull();
});

test('manual announcement selection does not send to every admin of a selected tenant', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);

    $tenant = createAnnouncementTenant('Mehrere Admins');

    User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email' => 'admin-one@example.test',
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email' => 'admin-two@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($operator)
        ->from(route('admin.announcements.create'))
        ->post(route('admin.announcements.store'), [
            'action' => 'send',
            'subject' => 'Clubano Update',
            'body_markdown' => '<p>Hallo</p>',
            'category' => OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE,
            'recipient_filter' => 'selected',
            'tenant_ids' => [$tenant->id],
        ])
        ->assertRedirect(route('admin.announcements.create'))
        ->assertSessionHas('error', 'Für diese Auswahl wurden keine Vereinsadmins gefunden.');

    expect(OperatorAnnouncementDelivery::query()->count())->toBe(0);
});

test('product update announcements skip admins who unsubscribed from operator updates', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);

    $tenant = createAnnouncementTenant('Opt-out Verein');

    $activeAdmin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email' => 'active-admin@example.test',
        'email_verified_at' => now(),
    ]);

    $unsubscribedAdmin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email' => 'unsubscribed-admin@example.test',
        'email_verified_at' => now(),
        'operator_updates_unsubscribed_at' => now(),
    ]);

    $this->actingAs($operator)
        ->post(route('admin.announcements.store'), [
            'action' => 'send',
            'subject' => 'Clubano Update',
            'body_markdown' => '<p>Hallo</p>',
            'category' => OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE,
            'recipient_filter' => 'selected',
            'recipient_user_ids' => [$activeAdmin->id, $unsubscribedAdmin->id],
        ])
        ->assertRedirect(route('admin.announcements.index'))
        ->assertSessionHas('success', 'Betreiber-Mitteilung versendet: 1 erfolgreich, 0 fehlgeschlagen, 1 wegen Abmeldung ausgeschlossen.');

    $announcement = OperatorAnnouncement::query()->latest()->firstOrFail();

    expect($announcement->deliveries()->count())->toBe(1)
        ->and($announcement->recipient_summary['excluded_by_opt_out'])->toBe(1)
        ->and($announcement->deliveries()->first()->email)->toBe('active-admin@example.test');
});

test('privacy announcements are still sent to admins who unsubscribed from product updates', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);

    $tenant = createAnnouncementTenant('Pflichtkommunikation Verein');

    $unsubscribedAdmin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email' => 'privacy-admin@example.test',
        'email_verified_at' => now(),
        'operator_updates_unsubscribed_at' => now(),
    ]);

    $this->actingAs($operator)
        ->post(route('admin.announcements.store'), [
            'action' => 'send',
            'subject' => 'Datenschutzhinweis',
            'body_markdown' => '<p>Wichtiger Hinweis</p>',
            'category' => OperatorAnnouncement::CATEGORY_PRIVACY,
            'recipient_filter' => 'selected',
            'recipient_user_ids' => [$unsubscribedAdmin->id],
        ])
        ->assertRedirect(route('admin.announcements.index'));

    $announcement = OperatorAnnouncement::query()->latest()->firstOrFail();

    expect($announcement->deliveries()->count())->toBe(1)
        ->and($announcement->recipient_summary['excluded_by_opt_out'])->toBe(0)
        ->and($announcement->deliveries()->first()->email)->toBe('privacy-admin@example.test');
});

test('operator announcement unsubscribe link disables future product updates for the user', function () {
    $tenant = createAnnouncementTenant('Abmeldung Verein');

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email' => 'unsubscribe@example.test',
        'email_verified_at' => now(),
    ]);

    $announcement = OperatorAnnouncement::create([
        'subject' => 'Produktupdate',
        'body_markdown' => '<p>Hallo</p>',
        'body_html' => '<p>Hallo</p>',
        'category' => OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE,
        'recipient_filter' => 'selected',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $delivery = OperatorAnnouncementDelivery::create([
        'operator_announcement_id' => $announcement->id,
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'recipient_name' => $user->name,
        'email' => $user->email,
        'status' => 'sent',
    ]);

    $unsubscribeUrl = URL::signedRoute('operator-announcements.unsubscribe', [
        'delivery' => $delivery->id,
        'token' => $delivery->tracking_token,
    ]);

    $this->get($unsubscribeUrl)
        ->assertOk()
        ->assertSee('Produktupdates abbestellt');

    expect($user->refresh()->operator_updates_unsubscribed_at)->not->toBeNull();
});

test('operator announcement opens and clicks are tracked', function () {
    $tenant = createAnnouncementTenant('Tracking Verein');

    $announcement = OperatorAnnouncement::create([
        'subject' => 'Tracking Update',
        'body_markdown' => '<p>Hallo</p>',
        'body_html' => '<p>Hallo</p>',
        'category' => OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE,
        'recipient_filter' => 'selected',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $delivery = OperatorAnnouncementDelivery::create([
        'operator_announcement_id' => $announcement->id,
        'tenant_id' => $tenant->id,
        'recipient_name' => 'Admin Tracking',
        'email' => 'tracking@example.test',
        'status' => 'sent',
    ]);

    $this->get(route('operator-announcements.tracking.open', $delivery->tracking_token))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    $delivery->refresh();

    expect($delivery->open_count)->toBe(1)
        ->and($delivery->first_opened_at)->not->toBeNull();

    $clickUrl = URL::signedRoute('operator-announcements.tracking.click', [
        'delivery' => $delivery->id,
        'target' => 'https://app.clubano.de/dashboard',
    ]);

    $this->get($clickUrl)
        ->assertRedirect('https://app.clubano.de/dashboard');

    $delivery->refresh();

    expect($delivery->click_count)->toBe(1)
        ->and($delivery->first_clicked_at)->not->toBeNull()
        ->and($delivery->last_clicked_url)->toBe('https://app.clubano.de/dashboard');
});
