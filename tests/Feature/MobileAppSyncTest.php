<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\MobileAppUser;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('admin can synchronize selected members into separated app accounts', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    [$admin, $tenant] = mobileSyncAdminFixture();
    $tag = Tag::create([
        'tenant_id' => $tenant->id,
        'name' => 'App User',
    ]);

    $included = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mara',
        'last_name' => 'Mobil',
        'email' => 'mara.sync@example.test',
    ]);
    $included->tags()->attach($tag->id);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'No',
        'last_name' => 'App',
        'email' => 'no.app@example.test',
    ]);

    $this->actingAs($admin)
        ->put(route('mobile-app-sync.update'), [
            'mobile_app_sync_enabled' => '1',
            'mobile_app_sync_tag_id' => $tag->id,
            'mobile_app_member_ids' => [$included->id],
        ])
        ->assertRedirect(route('mobile-app-sync.index'));

    $this->actingAs($admin)
        ->get(route('mobile-app-sync.index'))
        ->assertOk()
        ->assertSee('App-Synchronisierung')
        ->assertSee('1 Mitglied(er) werden aktuell synchronisiert')
        ->assertSee('1 von 2 Mitgliedern ausgewählt');

    $this->actingAs($admin)
        ->post(route('mobile-app-sync.run'), ['send_invitations' => '1'])
        ->assertRedirect(route('mobile-app-sync.index'));

    $appUser = MobileAppUser::query()->where('member_id', $included->id)->firstOrFail();

    expect($appUser->username)->toBe('mara.sync@example.test')
        ->and($appUser->sync_status)->toBe(MobileAppUser::STATUS_INVITED)
        ->and($appUser->invitation_token)->not->toBeNull()
        ->and(User::query()->where('email', 'mara.sync@example.test')->exists())->toBeFalse()
        ->and(MobileAppUser::query()->where('username', 'no.app@example.test')->exists())->toBeFalse();

    expect($appUser->invitation_sent_at)->not->toBeNull();
});

test('sync does not invite members unless they are explicitly selected', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    [$admin, $tenant] = mobileSyncAdminFixture();
    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Nicht',
        'last_name' => 'Ausgewaehlt',
        'email' => 'nicht.ausgewaehlt@example.test',
    ]);

    $this->actingAs($admin)
        ->put(route('mobile-app-sync.update'), [
            'mobile_app_sync_enabled' => '1',
            'mobile_app_member_ids' => [],
        ])
        ->assertRedirect(route('mobile-app-sync.index'));

    $this->actingAs($admin)
        ->post(route('mobile-app-sync.run'), ['send_invitations' => '1'])
        ->assertRedirect(route('mobile-app-sync.index'));

    expect(MobileAppUser::query()->where('username', 'nicht.ausgewaehlt@example.test')->exists())->toBeFalse();
});

test('invitation activation enables mobile login but not web login', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    [$admin, $tenant] = mobileSyncAdminFixture();
    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Lea',
        'last_name' => 'App',
        'email' => 'lea.app@example.test',
    ]);

    $tenant->forceFill(['mobile_app_sync_enabled' => true])->save();
    $member->forceFill(['mobile_app_sync_enabled' => true])->save();

    $this->actingAs($admin)
        ->post(route('mobile-app-sync.run'), ['send_invitations' => '1'])
        ->assertRedirect(route('mobile-app-sync.index'));

    $appUser = MobileAppUser::query()->where('member_id', $member->id)->firstOrFail();

    $this->get(route('mobile-app-invitations.show', $appUser->invitation_token))
        ->assertOk()
        ->assertSee('Mein Clubano aktivieren');

    $this->post(route('mobile-app-invitations.store', $appUser->invitation_token), [
        'password' => 'app-secret-123',
        'password_confirmation' => 'app-secret-123',
    ])->assertOk()
        ->assertSee('App-Zugang aktiviert');

    $appUser->refresh();

    expect($appUser->sync_status)->toBe(MobileAppUser::STATUS_ACTIVE)
        ->and($appUser->accepted_at)->not->toBeNull()
        ->and($appUser->invitation_token)->toBeNull();

    auth()->logout();
    $this->flushSession();

    $this->post('/login', [
        'email' => 'lea.app@example.test',
        'password' => 'app-secret-123',
    ])->assertSessionHasErrors('email');

    $this->postJson('/api/mobile/login', [
        'username' => 'lea.app@example.test',
        'password' => 'app-secret-123',
        'device_name' => 'iPhone',
    ])->assertOk()
        ->assertJsonPath('member.full_name', 'Lea App');
});

test('sync disables app accounts when members leave the selected segment without deleting data', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    [$admin, $tenant] = mobileSyncAdminFixture();
    $tag = Tag::create(['tenant_id' => $tenant->id, 'name' => 'App User']);
    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Tom',
        'last_name' => 'Segment',
        'email' => 'tom.segment@example.test',
        'mobile_app_sync_enabled' => true,
    ]);
    $member->tags()->attach($tag->id);

    $tenant->forceFill([
        'mobile_app_sync_enabled' => true,
        'mobile_app_sync_tag_id' => $tag->id,
    ])->save();

    $this->actingAs($admin)->post(route('mobile-app-sync.run'), ['send_invitations' => '0']);

    $appUser = MobileAppUser::query()->where('member_id', $member->id)->firstOrFail();
    $member->tags()->detach($tag->id);

    $this->actingAs($admin)
        ->post(route('mobile-app-sync.run'), ['send_invitations' => '0'])
        ->assertRedirect(route('mobile-app-sync.index'));

    $appUser->refresh();

    expect($appUser->is_active)->toBeFalse()
        ->and($appUser->sync_status)->toBe(MobileAppUser::STATUS_REMOVED)
        ->and(MobileAppUser::query()->where('member_id', $member->id)->exists())->toBeTrue();
});

function mobileSyncAdminFixture(): array
{
    $tenant = Tenant::create([
        'name' => 'Sync Verein',
        'slug' => 'sync-verein-' . bin2hex(random_bytes(3)),
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    return [$admin, $tenant];
}
