<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Protocol;
use App\Models\ProtocolEntry;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;

test('protocols can be created from structured entries and create tasks', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Protokoll Verein',
        'slug' => 'protokoll-verein',
        'email' => 'protokoll@example.test',
        'license_mode' => 'gifted',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $response = $this->actingAs($user)->post(route('protocols.store'), [
        'title' => 'Vorstandssitzung',
        'type' => 'Vorstandssitzung',
        'location' => 'Vereinsheim',
        'entries' => [
            [
                'type' => ProtocolEntry::TYPE_RESOLUTION,
                'title' => 'Kartoffelmarkt',
                'content' => 'Der Vorstand beschließt die Teilnahme am Kartoffelmarkt.',
                'visible_in_protocol' => '1',
            ],
            [
                'type' => ProtocolEntry::TYPE_TASK,
                'title' => 'Getränkeeinkauf organisieren',
                'content' => 'Dirk Radecker organisiert den Getränkeeinkauf.',
                'responsible_name' => 'Dirk Radecker',
                'due_date' => '2026-08-15',
                'visible_in_protocol' => '1',
            ],
        ],
    ]);

    $protocol = Protocol::query()->where('tenant_id', $tenant->id)->first();

    $response->assertRedirect(route('protocols.index'));
    expect($protocol)->not->toBeNull();
    expect($protocol->entries)->toHaveCount(2);
    expect($protocol->content)->toContain('Kartoffelmarkt');
    expect($protocol->content)->toContain('Getränkeeinkauf organisieren');

    $task = Task::query()
        ->where('tenant_id', $tenant->id)
        ->where('title', 'Getränkeeinkauf organisieren')
        ->first();

    expect($task)->not->toBeNull();
    expect($task->plan_end->toDateString())->toBe('2026-08-15');
    expect($task->related_type)->toBe(ProtocolEntry::class);
});
