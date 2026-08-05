<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function createInvitationTenantWithAdmin(): array
{
    $suffix = Str::random(6);

    $tenant = Tenant::create([
        'name' => 'Verein Einladung ' . $suffix,
        'slug' => 'verein-einladung-' . $suffix,
        'email' => 'einladung-' . $suffix . '@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $admin];
}

test('admins can invite selected members as users with a role', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Notification::fake();

    [$tenant, $admin] = createInvitationTenantWithAdmin();

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mara',
        'last_name' => 'Kasse',
        'email' => 'mara.kasse@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->post(route('users.invite-members.store'), [
            'member_ids' => [$member->id],
            'role' => User::ROLE_TREASURER,
        ])
        ->assertRedirect(route('users.index'));

    $invitedUser = User::query()->where('email', 'mara.kasse@example.test')->firstOrFail();

    expect($invitedUser->tenant_id)->toBe($tenant->id)
        ->and($invitedUser->name)->toBe('Mara Kasse')
        ->and($invitedUser->role)->toBe(User::ROLE_TREASURER)
        ->and($invitedUser->email_verified_at)->not->toBeNull();

    Notification::assertSentTo($invitedUser, ResetPassword::class, function (ResetPassword $notification) use ($invitedUser, $tenant) {
        $mail = $notification->toMail($invitedUser);

        expect($mail->subject)->toBe('Dein Zugang zu Clubano')
            ->and($mail->actionText)->toBe('Passwort festlegen')
            ->and(implode(' ', $mail->introLines))->toContain($tenant->name);

        return true;
    });
});

test('member invite page only offers active members without existing user account', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createInvitationTenantWithAdmin();

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Einladbar',
        'last_name' => 'Mitglied',
        'email' => 'einladbar@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ohne',
        'last_name' => 'Mail',
        'email' => null,
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Archiv',
        'last_name' => 'Mitglied',
        'email' => 'archiv@example.test',
        'entry_date' => now()->subYear()->toDateString(),
        'archived_at' => now(),
    ]);

    User::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bestehend',
        'email' => 'bestehend@example.test',
        'role' => User::ROLE_VIEWER,
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Bestehend',
        'last_name' => 'Benutzer',
        'email' => 'bestehend@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('users.invite-members'))
        ->assertOk()
        ->assertSee('Einladbar Mitglied')
        ->assertSee('Ohne Mail')
        ->assertSee('Keine E-Mail-Adresse hinterlegt')
        ->assertDontSee('Archiv Mitglied')
        ->assertSee('Bestehend Benutzer')
        ->assertSee('Benutzerzugang besteht bereits');
});

test('member invite page does not leak users from other tenants', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $admin] = createInvitationTenantWithAdmin();
    [$otherTenant] = createInvitationTenantWithAdmin();

    User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Kasse anderer Verein',
        'email' => 'kasse@ghg-sarstedt.de',
        'role' => User::ROLE_TREASURER,
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Neue',
        'last_name' => 'Kasse',
        'email' => 'kasse@ghg-sarstedt.de',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('users.invite-members'))
        ->assertOk()
        ->assertSee('Neue Kasse')
        ->assertDontSee('Benutzerzugang besteht bereits');
});

test('inviting a member with an account in another tenant does not attach that account', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Notification::fake();

    [$tenant, $admin] = createInvitationTenantWithAdmin();
    [$otherTenant] = createInvitationTenantWithAdmin();

    $existingUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Kasse anderer Verein',
        'email' => 'mehrverein@example.test',
        'role' => User::ROLE_TREASURER,
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mehr',
        'last_name' => 'Verein',
        'email' => 'mehrverein@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->post(route('users.invite-members.store'), [
            'member_ids' => [$member->id],
            'role' => User::ROLE_VIEWER,
        ])
        ->assertRedirect(route('users.index'));

    expect(User::query()->where('email', 'mehrverein@example.test')->count())->toBe(1);

    $existingUser->refresh();

    expect((string) $existingUser->tenant_id)->toBe((string) $otherTenant->id)
        ->and($existingUser->role)->toBe(User::ROLE_TREASURER);

    Notification::assertNothingSent();
});
