<?php

use App\Livewire\DashboardMemberStats;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('dashboard birthdays calculate the next age and ignore archived members', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));

    $suffix = Str::random(6);

    $tenant = Tenant::create([
        'name' => 'Geburtstagsverein ' . $suffix,
        'slug' => 'geburtstagsverein-' . $suffix,
        'email' => 'geburtstag-' . $suffix . '@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $birthdayMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mia',
        'last_name' => 'Geburtstag',
        'birthday' => '1980-12-01',
        'entry_date' => '2020-01-01',
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Archiv',
        'last_name' => 'Geburtstag',
        'birthday' => '1970-10-01',
        'entry_date' => '2020-01-01',
        'archived_at' => now(),
    ]);

    $component = Livewire::actingAs($admin)->test(DashboardMemberStats::class);
    $birthdays = $component->get('birthdays');

    expect($birthdays->pluck('id')->all())->toBe([$birthdayMember->id])
        ->and($birthdays->first()->next_birthday_date->toDateString())->toBe('2026-12-01')
        ->and($birthdays->first()->next_birthday_age)->toBe(46);

    Carbon::setTestNow();
});
