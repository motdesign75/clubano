<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\AppNewsItem;
use App\Models\DataUpdateRequest;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\EventShift;
use App\Models\EventShiftAssignment;
use App\Models\Member;
use App\Models\MobileAppUser;
use App\Models\Tenant;
use App\Models\User;

test('member can login and read own mobile profile', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'App Verein',
        'slug' => 'app-verein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mara',
        'last_name' => 'Mobil',
        'email' => 'mara@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    User::factory()->create([
        'tenant_id' => $tenant->id,
        'member_id' => $member->id,
        'name' => 'Mara Mobil',
        'email' => 'mara@example.test',
        'password' => 'web-password',
        'email_verified_at' => now(),
    ]);

    MobileAppUser::create([
        'tenant_id' => $tenant->id,
        'member_id' => $member->id,
        'username' => 'mara-app',
        'password' => 'secret-password',
        'is_active' => true,
    ]);

    $this->postJson('/api/mobile/login', [
        'username' => 'mara@example.test',
        'password' => 'web-password',
        'device_name' => 'iPhone',
    ])->assertStatus(422);

    $login = $this->postJson('/api/mobile/login', [
        'username' => 'mara-app',
        'password' => 'secret-password',
        'device_name' => 'iPhone',
    ])->assertOk();

    $token = $login->json('token');

    $this->withToken($token)
        ->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('tenant.name', 'App Verein')
        ->assertJsonPath('member.full_name', 'Mara Mobil');
});

test('member can submit profile changes without directly updating master data', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$user, $member] = mobileUserFixture();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/mobile/me/profile-change', [
            'first_name' => 'Mara',
            'last_name' => 'Mobil',
            'organization' => '',
            'email' => 'neu@example.test',
            'mobile' => '0171 123',
            'landline' => '',
            'street' => 'Neue Straße 2',
            'address_addition' => '',
            'zip' => '31157',
            'city' => 'Sarstedt',
            'country' => 'Deutschland',
            'change_note' => 'Neue Kontaktdaten.',
        ])
        ->assertCreated()
        ->assertJsonPath('changes_count', 3);

    $member->refresh();
    $dataUpdateRequest = DataUpdateRequest::query()->where('member_id', $member->id)->firstOrFail();

    expect($member->email)->toBe('mara@example.test')
        ->and($dataUpdateRequest->created_by)->toBeNull()
        ->and($dataUpdateRequest->status)->toBe(DataUpdateRequest::STATUS_SUBMITTED);
});

test('member can see events and respond without using paid booking flow', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$user, $member, $tenant] = mobileUserFixture();

    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Sommerfest',
        'location' => 'Vereinsheim',
        'start' => now()->addWeek(),
        'end' => now()->addWeek()->addHours(3),
        'is_public' => true,
        'response_required' => true,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/events')
        ->assertOk()
        ->assertJsonPath('events.0.title', 'Sommerfest');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/mobile/events/{$event->id}/response", [
            'status' => EventInvitation::STATUS_ACCEPTED,
            'note' => 'Ich komme.',
        ])
        ->assertOk()
        ->assertJsonPath('invitation.status', EventInvitation::STATUS_ACCEPTED);

    expect(EventInvitation::query()
        ->where('event_id', $event->id)
        ->where('member_id', $member->id)
        ->where('status', EventInvitation::STATUS_ACCEPTED)
        ->exists())->toBeTrue();
});

test('mobile shifts are only exposed for events with app approval', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$user, $member, $tenant] = mobileUserFixture();

    $hiddenEvent = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Interner Aufbau',
        'start' => now()->addDays(2),
        'end' => now()->addDays(2)->addHours(2),
        'is_public' => false,
        'mobile_shifts_enabled' => false,
    ]);

    EventShift::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'event_id' => $hiddenEvent->id,
        'title' => 'Aufbau',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
        'required_people' => 1,
    ]);

    $visibleEvent = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'App Dienst',
        'start' => now()->addDays(3),
        'end' => now()->addDays(3)->addHours(2),
        'is_public' => false,
        'mobile_shifts_enabled' => true,
    ]);

    $shift = EventShift::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'event_id' => $visibleEvent->id,
        'title' => 'Tresen',
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHour(),
        'required_people' => 2,
    ]);

    EventShiftAssignment::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'event_id' => $visibleEvent->id,
        'event_shift_id' => $shift->id,
        'member_id' => $member->id,
        'status' => 'confirmed',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/shifts')
        ->assertOk()
        ->assertJsonCount(1, 'events')
        ->assertJsonPath('events.0.title', 'App Dienst')
        ->assertJsonPath('events.0.shifts.0.assignments.0.is_me', true);
});

test('mobile documents only expose bylaws and contribution rules', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$user, , $tenant] = mobileUserFixture();

    Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Satzung',
        'status' => Document::STATUS_ACTIVE,
        'category' => Document::CATEGORY_CLUB,
        'disk' => 'local',
        'path' => 'satzung.pdf',
        'original_name' => 'satzung.pdf',
    ]);

    Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Protokoll Vorstand',
        'status' => Document::STATUS_ACTIVE,
        'category' => Document::CATEGORY_PROTOCOLS,
        'disk' => 'local',
        'path' => 'protokoll.pdf',
        'original_name' => 'protokoll.pdf',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/documents')
        ->assertOk()
        ->assertJsonCount(1, 'documents')
        ->assertJsonPath('documents.0.title', 'Satzung');
});

test('mobile news only exposes published items from own tenant', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$user, , $tenant] = mobileUserFixture();

    AppNewsItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Neue Vereinsinfo',
        'teaser' => 'Wichtig für alle Mitglieder.',
        'body' => 'Bitte am Wochenende beachten.',
        'status' => AppNewsItem::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
        'push_enabled' => true,
    ]);

    AppNewsItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Noch Entwurf',
        'status' => AppNewsItem::STATUS_DRAFT,
        'published_at' => now()->subMinute(),
    ]);

    $otherTenant = Tenant::create([
        'name' => 'Anderer Verein',
        'slug' => 'anderer-verein-' . bin2hex(random_bytes(3)),
        'email' => 'anderer@example.test',
        'license_mode' => 'gifted',
    ]);

    AppNewsItem::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'title' => 'Fremde News',
        'status' => AppNewsItem::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/news')
        ->assertOk()
        ->assertJsonCount(1, 'news')
        ->assertJsonPath('news.0.title', 'Neue Vereinsinfo')
        ->assertJsonPath('news.0.push_enabled', true);
});

test('member card exposes signed identity qr without personal contact data', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$user, $member] = mobileUserFixture();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/member-card')
        ->assertOk()
        ->assertJsonPath('card.full_name', 'Mara Mobil');

    $payload = $response->json('card.qr_payload');

    expect(str_starts_with($payload, 'clubano://member/v1/'))->toBeTrue()
        ->and(str_contains($payload, 'mara@example.test'))->toBeFalse()
        ->and(str_starts_with($response->json('card.qr_code_data_uri'), 'data:image/png;base64,'))->toBeTrue();

    $member->refresh();
    $oldPayload = $member->mobileIdentityPayload();

    $member->rotateMobileIdentity();
    $member->refresh();

    expect($member->mobileIdentityPayload())->not->toBe($oldPayload);
});

test('trinkwert can resolve a valid clubano member identity with integration token', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    config(['services.trinkwert.integration_token' => 'test-trinkwert-token']);

    [, $member, $tenant] = mobileUserFixture();
    $payload = $member->mobileIdentityPayload();

    $this->postJson('/api/integrations/trinkwert/resolve-member', [
        'qr_payload' => $payload,
    ])->assertUnauthorized();

    $this->withToken('test-trinkwert-token')
        ->postJson('/api/integrations/trinkwert/resolve-member', [
            'qr_payload' => $payload,
        ])
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('tenant.name', $tenant->name)
        ->assertJsonPath('member.full_name', 'Mara Mobil')
        ->assertJsonPath('member.identity_uuid', $member->fresh()->mobile_identity_uuid);
});

test('trinkwert rejects manipulated clubano member identity payloads', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    config(['services.trinkwert.integration_token' => 'test-trinkwert-token']);

    [, $member] = mobileUserFixture();
    $payload = $member->mobileIdentityPayload();
    $manipulated = preg_replace('/[a-f0-9]{64}$/', str_repeat('0', 64), $payload);

    $this->withToken('test-trinkwert-token')
        ->postJson('/api/integrations/trinkwert/resolve-member', [
            'qr_payload' => $manipulated,
        ])
        ->assertNotFound()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('reason', 'invalid_signature');
});

function mobileUserFixture(): array
{
    $tenant = Tenant::create([
        'name' => 'App Verein',
        'slug' => 'app-verein-' . bin2hex(random_bytes(3)),
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mara',
        'last_name' => 'Mobil',
        'email' => 'mara@example.test',
        'mobile' => '0170 000',
        'street' => 'Alte Straße 1',
        'zip' => '31157',
        'city' => 'Sarstedt',
        'country' => 'Deutschland',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $user = MobileAppUser::create([
        'tenant_id' => $tenant->id,
        'member_id' => $member->id,
        'username' => 'mara-app-' . bin2hex(random_bytes(3)),
        'password' => 'secret-password',
        'is_active' => true,
    ]);

    return [$user, $member, $tenant];
}
