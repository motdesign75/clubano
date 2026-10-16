<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\AppNotification;
use App\Models\AppNewsItem;
use App\Models\DataUpdateRequest;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\EventShift;
use App\Models\EventShiftAssignment;
use App\Models\Member;
use App\Models\MobileAppUser;
use App\Models\MobilePushToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MobileNotificationService;
use Illuminate\Support\Facades\Http;

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

test('mobile app can register push token and read notifications', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$user, $member, $tenant] = mobileUserFixture();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/mobile/push-token', [
            'token' => 'ExponentPushToken[test-token]',
            'provider' => 'expo',
            'platform' => 'ios',
            'device_name' => 'iPhone',
        ])
        ->assertOk();

    expect(MobilePushToken::query()
        ->where('mobile_app_user_id', $user->id)
        ->where('token', 'ExponentPushToken[test-token]')
        ->where('provider', 'expo')
        ->exists())->toBeTrue();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/mobile/push-token', [
            'token' => 'abc123native',
            'provider' => 'apns',
            'platform' => 'ios',
            'device_name' => 'iPhone',
        ])
        ->assertOk();

    expect(MobilePushToken::query()
        ->where('mobile_app_user_id', $user->id)
        ->where('token', 'abc123native')
        ->where('provider', 'apns')
        ->exists())->toBeTrue();

    $notification = AppNotification::create([
        'tenant_id' => $tenant->id,
        'mobile_app_user_id' => $user->id,
        'member_id' => $member->id,
        'type' => AppNotification::TYPE_APP_NEWS,
        'title' => 'Neue Info',
        'body' => 'Bitte lesen.',
        'sent_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/mobile/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.title', 'Neue Info');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/mobile/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('notification.read_at', fn ($value) => filled($value));
});

test('published app news is not marked as pushed when no device tokens exist', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Http::fake();

    [, , $tenant] = mobileUserFixture();

    $news = AppNewsItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Wichtige News ohne Token',
        'teaser' => 'Kurze Zusammenfassung',
        'status' => AppNewsItem::STATUS_PUBLISHED,
        'published_at' => now(),
        'push_enabled' => true,
    ]);

    app(MobileNotificationService::class)->notifyNews($news);

    expect($news->fresh()->push_sent_at)->toBeNull();
    Http::assertNothingSent();
});

test('published app news creates mobile notifications and sends expo push', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([['status' => 'ok']], 200),
    ]);

    [$user, , $tenant] = mobileUserFixture();

    MobilePushToken::create([
        'tenant_id' => $tenant->id,
        'mobile_app_user_id' => $user->id,
        'member_id' => $user->member_id,
        'token' => 'ExponentPushToken[test-token]',
        'platform' => 'ios',
        'last_seen_at' => now(),
    ]);

    $news = AppNewsItem::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Wichtige News',
        'teaser' => 'Kurze Zusammenfassung',
        'status' => AppNewsItem::STATUS_PUBLISHED,
        'published_at' => now(),
        'push_enabled' => true,
    ]);

    $created = app(MobileNotificationService::class)->notifyNews($news);

    expect($created)->toBe(1)
        ->and(AppNotification::query()->where('app_news_item_id', $news->id)->count())->toBe(1);

    Http::assertSent(fn ($request) => $request->url() === 'https://exp.host/--/api/v2/push/send');
});

test('mobile password reset revokes sessions and cannot be reused or change the web password', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    $webUser = User::factory()->create(['tenant_id' => $tenant->id, 'email' => $member->email, 'password' => 'web-password']);
    $user->createToken('phone');
    $token = \Illuminate\Support\Facades\Password::broker('mobile')->createToken($user);
    $payload = ['username' => $user->username, 'token' => $token, 'password' => 'new-app-password', 'password_confirmation' => 'new-app-password'];

    $this->get(route('mobile-password.reset', ['token' => $token, 'username' => $user->username]))->assertOk();
    $this->post(route('mobile-password.update'), $payload)->assertSessionHasNoErrors()->assertSessionHas('status');

    expect(\Illuminate\Support\Facades\Hash::check('new-app-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0)
        ->and(\Illuminate\Support\Facades\Hash::check('web-password', $webUser->fresh()->password))->toBeTrue();
    $this->post(route('mobile-password.update'), $payload)->assertSessionHasErrors('password');
});

test('unknown mobile password requests do not reveal account existence', function () {
    $this->postJson('/api/mobile/forgot-password', ['username' => 'unknown'])
        ->assertOk()->assertJsonStructure(['message']);
});

test('disabled mobile accounts cannot reset their password', function () {
    [$user] = mobileUserFixture();
    $token = \Illuminate\Support\Facades\Password::broker('mobile')->createToken($user);
    $user->update(['is_active' => false]);
    $this->post(route('mobile-password.update'), [
        'username' => $user->username, 'token' => $token,
        'password' => 'new-app-password', 'password_confirmation' => 'new-app-password',
    ])->assertSessionHasErrors('password');
    expect(\Illuminate\Support\Facades\Hash::check('secret-password', $user->fresh()->password))->toBeTrue();
});

test('mobile booking events use the existing form and reject simple responses', function () {
    [$user, , $tenant] = mobileUserFixture();
    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'title' => 'Buchbarer Termin',
        'start' => now()->addWeek(), 'end' => now()->addWeek()->addHour(),
        'booking_enabled' => true, 'response_required' => true,
        'registration_deadline' => now()->addDays(3), 'max_participants_total' => 10,
        'show_remaining_spots' => true,
    ]);
    $form = \App\Models\PublicForm::create([
        'tenant_id' => $tenant->id, 'event_id' => $event->id, 'title' => 'Anmeldung',
        'slug' => 'mobile-booking-test', 'form_type' => 'event', 'is_active' => true,
    ]);
    $this->actingAs($user, 'sanctum')->getJson('/api/mobile/events')
        ->assertOk()->assertJsonPath('events.0.booking_url', route('forms.public.show', $form->slug))
        ->assertJsonPath('events.0.remaining_spots', 10);
    $this->postJson("/api/mobile/events/{$event->id}/response", ['status' => 'accepted'])->assertStatus(422);
    expect(EventInvitation::where('event_id', $event->id)->count())->toBe(0);

    $event->update(['registration_deadline' => now()->subMinute()]);
    $this->getJson("/api/mobile/events/{$event->id}")->assertOk()
        ->assertJsonPath('event.booking_url', null)
        ->assertJsonPath('event.booking_closed_reason', 'Der Anmeldeschluss ist erreicht.');
});

test('mobile registration status only exposes the linked members active booking', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'title' => 'Termin', 'booking_enabled' => true,
        'start' => now()->addWeek(), 'end' => now()->addWeek()->addHour(),
    ]);
    $booking = \App\Models\EventBooking::create([
        'tenant_id' => $tenant->id, 'event_id' => $event->id,
        'booking_reference' => 'TEST-APP-123', 'booker_name' => 'Mara',
        'booker_email' => $member->email, 'participant_count' => 1,
        'price_per_person' => 0, 'total_amount' => 0, 'booking_status' => 'pending',
        'payment_status' => 'not_required', 'currency' => 'EUR',
    ]);
    $participant = $booking->participants()->create([
        'member_id' => $member->id, 'participant_type' => 'member', 'position' => 1,
        'first_name' => 'Mara', 'last_name' => 'Mobil', 'price_amount' => 0,
        'payment_status' => 'not_required',
    ]);
    $this->actingAs($user, 'sanctum')->getJson('/api/mobile/events')
        ->assertOk()->assertJsonPath('events.0.registration.reference', 'TEST-APP-123')
        ->assertJsonPath('events.0.registration.label', 'Anmeldung eingegangen')
        ->assertJsonPath('events.0.booking_url', null);
    $participant->update(['member_id' => null]);
    $this->getJson('/api/mobile/events')->assertJsonPath('events.0.registration', null);
    $participant->update(['member_id' => $member->id, 'payment_status' => 'cancelled']);
    $this->getJson('/api/mobile/events')->assertJsonPath('events.0.registration', null);
});

test('mobile cancellation releases only own free place and is idempotent', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    [$event, $booking, $participant] = mobileCancellationFixture($member, $tenant);
    $guest = $booking->participants()->create([
        'first_name' => 'Gast', 'last_name' => 'Bleibt', 'position' => 2,
        'participant_type' => 'free', 'payment_required' => false,
        'price_amount' => 0, 'payment_status' => 'not_required',
    ]);
    $booking->recalculateTotalsFromParticipants();
    $url = "/api/mobile/events/{$event->id}/registrations/{$participant->id}/cancel";
    $this->actingAs($user, 'sanctum')->getJson("/api/mobile/events/{$event->id}")
        ->assertJsonPath('event.registration.can_cancel', true);
    $this->postJson($url)->assertOk();
    $this->postJson($url)->assertOk();
    expect($participant->fresh()->payment_status)->toBe('cancelled')
        ->and($guest->fresh()->payment_status)->toBe('not_required')
        ->and($booking->fresh()->participant_count)->toBe(1)
        ->and($booking->fresh()->booking_status)->toBe('confirmed')
        ->and($event->fresh()->remaining_spots)->toBe(9)
        ->and(\App\Models\EventChangeLog::where('event_id', $event->id)->count())->toBe(1);
});

test('mobile cancellation closes empty booking and preserves its record', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    [$event, $booking, $participant] = mobileCancellationFixture($member, $tenant);
    $this->actingAs($user, 'sanctum')->postJson("/api/mobile/events/{$event->id}/registrations/{$participant->id}/cancel")->assertOk();
    expect($booking->fresh()->booking_status)->toBe('cancelled')
        ->and($booking->fresh()->participant_count)->toBe(0)
        ->and($event->fresh()->remaining_spots)->toBe(10);
});

test('mobile cancellation rejects paid bookings and past events', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    [$event, $booking, $participant] = mobileCancellationFixture($member, $tenant);
    $booking->update(['total_amount' => 25, 'gross_amount' => 25, 'payment_status' => 'paid']);
    $url = "/api/mobile/events/{$event->id}/registrations/{$participant->id}/cancel";
    $this->actingAs($user, 'sanctum')->getJson("/api/mobile/events/{$event->id}")
        ->assertJsonPath('event.registration.can_cancel', false);
    $this->postJson($url)->assertStatus(422);
    expect($participant->fresh()->payment_status)->toBe('not_required')
        ->and((float) $booking->fresh()->total_amount)->toBe(25.0);
    $booking->update(['total_amount' => 0, 'gross_amount' => 0, 'payment_status' => 'not_required']);
    $event->update(['start' => now()->subHour()]);
    $this->postJson($url)->assertStatus(422);
    expect($participant->fresh()->payment_status)->toBe('not_required');
});

test('mobile cancellation leaves linked invoices untouched even with zero booking total', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    [$event, $booking, $participant] = mobileCancellationFixture($member, $tenant);
    $invoice = \App\Models\Invoice::create([
        'tenant_id' => $tenant->id, 'invoice_number' => 'APP-CANCEL-TEST',
        'invoice_date' => now()->toDateString(), 'recipient_type' => 'free',
        'recipient_name' => $member->full_name, 'status' => 'open',
    ]);
    $booking->update(['invoice_id' => $invoice->id]);
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/mobile/events/{$event->id}/registrations/{$participant->id}/cancel")->assertStatus(422);
    expect($invoice->fresh()->status)->toBe('open')
        ->and($booking->fresh()->invoice_id)->toBe($invoice->id)
        ->and($participant->fresh()->payment_status)->toBe('not_required');
});

test('mobile cancellation cannot change another members or tenants registration', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    [$event, $booking, $participant] = mobileCancellationFixture($member, $tenant);
    $guest = $booking->participants()->create([
        'first_name' => 'Gast', 'last_name' => 'Fremd', 'position' => 2, 'participant_type' => 'free',
        'price_amount' => 0, 'payment_status' => 'not_required',
    ]);
    $this->actingAs($user, 'sanctum')->postJson("/api/mobile/events/{$event->id}/registrations/{$guest->id}/cancel")->assertNotFound();
    [, $otherMember, $otherTenant] = mobileUserFixture();
    [$otherEvent, , $otherParticipant] = mobileCancellationFixture($otherMember, $otherTenant);
    $this->postJson("/api/mobile/events/{$otherEvent->id}/registrations/{$otherParticipant->id}/cancel")->assertNotFound();
    expect($guest->fresh()->payment_status)->toBe('not_required')
        ->and($otherParticipant->fresh()->payment_status)->toBe('not_required');
});

test('native mobile booking uses authenticated member and shared pricing with additional guests', function () {
    \Illuminate\Support\Facades\Mail::fake();
    [$user, $member, $tenant] = mobileUserFixture();
    $event = mobileNativeBookingFixture($tenant);
    $event->update(['price_per_person' => 20, 'member_pricing_enabled' => true, 'member_price_per_person' => 5]);
    $this->actingAs($user, 'sanctum')->getJson("/api/mobile/events/{$event->id}/booking")
        ->assertOk()->assertJsonPath('member_price_cents', 500)->assertJsonPath('guest_price_cents', 2000);
    $payload = [
        'fields' => ['first_name' => 'Gefälscht', 'last_name' => 'Fremd', 'email' => $member->email, 'street' => 'Test 1', 'zip' => '31157', 'city' => 'Sarstedt', 'consent' => true],
        'participant_count' => 2, 'expected_total_cents' => 2500,
        'participants' => [['first_name' => 'Gast', 'last_name' => 'Test', 'participant_type' => 'member', 'member_id' => $member->id, 'price_amount' => 0]],
    ];
    $this->postJson("/api/mobile/events/{$event->id}/booking", $payload)->assertCreated()->assertJsonStructure(['reference']);
    $booking = \App\Models\EventBooking::where('event_id', $event->id)->with('participants')->firstOrFail();
    expect((float) $booking->total_amount)->toBe(25.0)
        ->and($booking->participants[0]->member_id)->toBe($member->id)
        ->and($booking->participants[0]->first_name)->toBe($member->first_name)
        ->and($booking->participants[1]->member_id)->toBeNull()
        ->and((float) $booking->participants[1]->price_amount)->toBe(20.0)
        ->and($booking->invoice_id)->not->toBeNull();
    $this->postJson("/api/mobile/events/{$event->id}/booking", $payload)->assertStatus(422);
    expect(\App\Models\EventBooking::where('event_id', $event->id)->count())->toBe(1);
});

test('native mobile booking checks required fields capacity and changed prices without creating bookings', function () {
    \Illuminate\Support\Facades\Mail::fake();
    [$user, $member, $tenant] = mobileUserFixture();
    $event = mobileNativeBookingFixture($tenant);
    $payload = ['fields' => ['email' => $member->email], 'participant_count' => 1, 'expected_total_cents' => 0];
    $this->actingAs($user, 'sanctum')->postJson("/api/mobile/events/{$event->id}/booking", $payload)
        ->assertStatus(422)->assertJsonValidationErrors('fields.consent');
    $payload['fields']['consent'] = true;
    $payload['participant_count'] = 6;
    $this->postJson("/api/mobile/events/{$event->id}/booking", $payload)->assertStatus(422);
    $payload['participant_count'] = 1;
    $event->update(['price_per_person' => 10, 'member_pricing_enabled' => false]);
    $payload['fields'] += ['street' => 'Test 1', 'zip' => '31157', 'city' => 'Sarstedt'];
    $this->postJson("/api/mobile/events/{$event->id}/booking", $payload)
        ->assertStatus(422)->assertJsonValidationErrors('price');
    expect(\App\Models\EventBooking::where('event_id', $event->id)->count())->toBe(0)
        ->and(\App\Models\PublicFormSubmission::where('event_id', $event->id)->count())->toBe(0);
    $event->update(['registration_deadline' => now()->subMinute()]);
    $this->getJson("/api/mobile/events/{$event->id}/booking")->assertStatus(422);
});

test('native mobile booking cannot access another tenants form', function () {
    [$user] = mobileUserFixture();
    [, , $tenant] = mobileUserFixture();
    $event = mobileNativeBookingFixture($tenant);
    $this->actingAs($user, 'sanctum')->getJson("/api/mobile/events/{$event->id}/booking")->assertNotFound();
    $this->postJson("/api/mobile/events/{$event->id}/booking", [])->assertNotFound();
});

test('mobile waitlist is scoped idempotent and notifies without reserving a place', function () {
    \Illuminate\Support\Facades\Mail::fake();
    [$user, $member, $tenant] = mobileUserFixture();
    $event = mobileNativeBookingFixture($tenant);
    $url = "/api/mobile/events/{$event->id}/waitlist";
    $this->actingAs($user, 'sanctum')->postJson($url, ['join' => true])->assertStatus(422);
    [, $booking, $participant] = mobileCancellationFixture($member, $tenant);
    $booking->update(['event_id' => $event->id]);
    $participant->update(['member_id' => null]);
    $event->update(['max_participants_total' => 1]);
    $this->postJson($url, ['join' => true])->assertOk();
    $this->postJson($url, ['join' => true])->assertOk();
    expect(\App\Models\EventWaitlistEntry::count())->toBe(1);
    $service = app(\App\Services\EventWaitlistService::class);
    expect($service->notifyAvailable($event))->toBe(0);
    $participant->update(['payment_status' => 'cancelled']);
    expect($service->notifyAvailable($event))->toBe(1)
        ->and($service->notifyAvailable($event))->toBe(0)
        ->and(\App\Models\AppNotification::where('type', 'event_waitlist')->count())->toBe(1)
        ->and($booking->fresh()->invoice_id)->toBeNull();
    $this->postJson("/api/mobile/events/{$event->id}/booking", [
        'fields' => ['email' => $member->email, 'consent' => true],
        'participant_count' => 1, 'expected_total_cents' => 0,
    ])->assertCreated();
    expect(\App\Models\EventWaitlistEntry::first()->status)->toBe('booked');
    $this->postJson($url, ['join' => true])->assertStatus(422);
});

test('mobile waitlist allows leaving but rejects foreign tenants and closed deadlines', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    $event = mobileNativeBookingFixture($tenant);
    [, $booking, $participant] = mobileCancellationFixture($member, $tenant);
    $booking->update(['event_id' => $event->id]);
    $participant->update(['member_id' => null]);
    $event->update(['max_participants_total' => 1]);
    $url = "/api/mobile/events/{$event->id}/waitlist";
    $this->actingAs($user, 'sanctum')->postJson($url, ['join' => true])->assertOk();
    $event->update(['registration_deadline' => now()->subMinute()]);
    $participant->update(['payment_status' => 'cancelled']);
    expect(app(\App\Services\EventWaitlistService::class)->notifyAvailable($event))->toBe(0);
    $this->postJson($url, ['join' => true])->assertStatus(422);
    $this->postJson($url, ['join' => false])->assertOk();
    $this->postJson($url, ['join' => false])->assertOk();
    expect(\App\Models\EventWaitlistEntry::first()->status)->toBe('removed');
    [$otherUser] = mobileUserFixture();
    $this->actingAs($otherUser, 'sanctum')->postJson($url, ['join' => true])->assertNotFound();
});

test('waitlist scheduler skips inactive users and sends once after reactivation', function () {
    [$user, $member, $tenant] = mobileUserFixture();
    $event = mobileNativeBookingFixture($tenant);
    $entry = \App\Models\EventWaitlistEntry::create([
        'tenant_id' => $tenant->id, 'event_id' => $event->id, 'member_id' => $member->id,
        'status' => 'waiting', 'joined_at' => now(),
    ]);
    $user->update(['is_active' => false]);
    $this->artisan('clubano:notify-event-waitlists')->assertSuccessful();
    expect($entry->fresh()->notified_at)->toBeNull();
    $user->update(['is_active' => true]);
    $this->artisan('clubano:notify-event-waitlists')->assertSuccessful();
    $this->artisan('clubano:notify-event-waitlists')->assertSuccessful();
    expect(\App\Models\AppNotification::where('type', 'event_waitlist')->count())->toBe(1)
        ->and($entry->fresh()->notified_at)->not->toBeNull()
        ->and(\App\Models\EventBooking::where('event_id', $event->id)->count())->toBe(0);
});

function mobileNativeBookingFixture(Tenant $tenant): Event
{
    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'title' => 'App Anmeldung', 'booking_enabled' => true,
        'start' => now()->addWeek(), 'end' => now()->addWeek()->addHour(),
        'max_participants_total' => 5, 'max_participants_per_booking' => 5, 'price_per_person' => 0,
    ]);
    $form = \App\Models\PublicForm::create([
        'tenant_id' => $tenant->id, 'event_id' => $event->id, 'title' => 'App Anmeldung',
        'slug' => 'app-' . bin2hex(random_bytes(5)), 'form_type' => 'event', 'is_active' => true,
    ]);
    foreach (['first_name', 'last_name', 'email', 'consent'] as $index => $slug) {
        $form->fields()->create([
            'label' => $slug, 'slug' => $slug, 'field_type' => $slug === 'consent' ? 'checkbox' : 'text',
            'is_required' => true, 'sort_order' => $index,
        ]);
    }

    return $event;
}

function mobileCancellationFixture(Member $member, Tenant $tenant): array
{
    $event = Event::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'title' => 'Kostenloser Termin', 'booking_enabled' => true,
        'start' => now()->addWeek(), 'end' => now()->addWeek()->addHour(), 'max_participants_total' => 10,
    ]);
    $booking = \App\Models\EventBooking::create([
        'tenant_id' => $tenant->id, 'event_id' => $event->id, 'booking_reference' => 'APP-' . bin2hex(random_bytes(5)),
        'booker_name' => $member->full_name, 'booker_email' => $member->email,
        'participant_count' => 1, 'price_per_person' => 0, 'gross_amount' => 0,
        'voucher_discount_amount' => 0, 'total_amount' => 0, 'currency' => 'EUR',
        'payment_status' => 'not_required', 'booking_status' => 'confirmed',
    ]);
    $participant = $booking->participants()->create([
        'member_id' => $member->id, 'first_name' => $member->first_name, 'last_name' => $member->last_name,
        'participant_type' => 'member', 'position' => 1, 'payment_required' => false,
        'price_amount' => 0, 'payment_status' => 'not_required',
    ]);

    return [$event, $booking, $participant];
}

function mobileUserFixture(): array
{
    $tenant = Tenant::create([
        'name' => 'App Verein',
        'slug' => 'app-verein-' . bin2hex(random_bytes(3)),
        'email' => 'verein-' . bin2hex(random_bytes(4)) . '@example.test',
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
