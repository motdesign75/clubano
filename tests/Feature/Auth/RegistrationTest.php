<?php

use App\Models\Tenant;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $this->get('/register');
    $this->travel(5)->seconds();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $tenant = Tenant::findOrFail($user->tenant_id);

    expect($user->role)->toBe(User::ROLE_ADMIN);
    expect($user->isSuperAdmin())->toBeFalse();
    expect($tenant->license_mode)->toBe('standard');
});

test('tenant bound superadmins are demoted to club admins by the safety migration', function () {
    $tenant = Tenant::create([
        'name' => 'Unsicherer Verein',
        'slug' => 'unsicherer-verein',
        'email' => 'unsicher@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_SUPERADMIN,
    ]);

    expect($user->tenant_id)->toBe($tenant->id);
    expect($user->role)->toBe(User::ROLE_SUPERADMIN);

    $migration = include database_path('migrations/2026_07_25_122000_demote_tenant_superadmins_to_admin.php');
    $migration->up();

    expect($user->fresh()->role)->toBe(User::ROLE_ADMIN);
});
