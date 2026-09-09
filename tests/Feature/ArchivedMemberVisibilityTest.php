<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

test('archived members stay out of visible member lists and counters', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $suffix = Str::random(6);

    $tenant = Tenant::create([
        'name' => 'Archivtest ' . $suffix,
        'slug' => 'archivtest-' . $suffix,
        'email' => 'archivtest-' . $suffix . '@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Aktiv',
        'amount' => 79,
        'interval' => 'jährlich',
    ]);

    $tag = Tag::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Vorstand',
        'color' => '#0f766e',
    ]);

    $active = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ada',
        'last_name' => 'Aktiv',
        'email' => 'ada-' . $suffix . '@example.test',
        'member_id' => 'A-' . $suffix,
        'entry_date' => now()->subYear()->toDateString(),
        'membership_id' => $membership->id,
    ]);

    $archived = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Arno',
        'last_name' => 'Archiv',
        'email' => 'arno-' . $suffix . '@example.test',
        'member_id' => 'R-' . $suffix,
        'entry_date' => now()->subYear()->toDateString(),
        'membership_id' => $membership->id,
        'archived_at' => now(),
    ]);

    $active->tags()->attach($tag);
    $archived->tags()->attach($tag);

    $membersResponse = $this->actingAs($admin)->get(route('members.index'));
    $membersResponse->assertOk();

    expect(collect($membersResponse->viewData('members')->items())->pluck('id')->all())
        ->toBe([$active->id])
        ->and($membersResponse->viewData('stats')['alle'])->toBe(1)
        ->and($membersResponse->viewData('stats')['archiviert'])->toBe(1);

    $membershipResponse = $this->actingAs($admin)->get(route('memberships.index'));
    $membershipResponse->assertOk();

    expect($membershipResponse->viewData('memberships')->firstWhere('id', $membership->id)->members_count)
        ->toBe(1)
        ->and($membershipResponse->viewData('billingMembers')->pluck('member.id')->all())
        ->toBe([$active->id]);

    $tagResponse = $this->actingAs($admin)->get(route('tags.index'));
    $tagResponse->assertOk();

    expect($tagResponse->viewData('tags')->firstWhere('id', $tag->id)->members_count)
        ->toBe(1);
});
