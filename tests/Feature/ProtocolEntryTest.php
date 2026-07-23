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

test('protocols can be created from quick notes', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Mitschrift Verein',
        'slug' => 'mitschrift-verein',
        'email' => 'mitschrift@example.test',
        'license_mode' => 'gifted',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $notes = implode("\n", [
        '- Vorsitzender berichtet über Renovierung',
        '- Beschluss: Teilnahme am Kartoffelmarkt',
        '- Dirk organisiert Getränke bis 15.08.2026',
    ]);

    $response = $this->actingAs($user)->post(route('protocols.store'), [
        'title' => 'Schnelle Sitzung',
        'type' => 'Vorstandssitzung',
        'raw_notes' => $notes,
    ]);

    $protocol = Protocol::query()->where('tenant_id', $tenant->id)->first();

    $response->assertRedirect(route('protocols.index'));
    expect($protocol)->not->toBeNull();
    expect($protocol->raw_notes)->toBe($notes);
    expect($protocol->entries)->toHaveCount(3);
    expect($protocol->entries->pluck('type')->all())->toContain(ProtocolEntry::TYPE_RESOLUTION);
    expect($protocol->entries->pluck('type')->all())->toContain(ProtocolEntry::TYPE_TASK);
});

test('protocols can be prepared from an agenda', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Agenda Verein',
        'slug' => 'agenda-verein',
        'email' => 'agenda@example.test',
        'license_mode' => 'gifted',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
    ]);

    $agenda = implode("\n", [
        'TOP 1 Begrüßung',
        'TOP 2 Renovierung Vereinsheim',
        'TOP 3 Kartoffelmarkt',
    ]);

    $response = $this->actingAs($user)->post(route('protocols.store'), [
        'title' => 'Agenda-Sitzung',
        'type' => 'Vorstandssitzung',
        'raw_agenda' => $agenda,
    ]);

    $protocol = Protocol::query()->where('tenant_id', $tenant->id)->first();

    $response->assertRedirect(route('protocols.index'));
    expect($protocol)->not->toBeNull();
    expect($protocol->raw_agenda)->toBe($agenda);
    expect($protocol->entries)->toHaveCount(3);
    expect($protocol->entries->pluck('title')->all())->toContain('Begrüßung');
    expect($protocol->entries->pluck('title')->all())->toContain('Renovierung Vereinsheim');
});
