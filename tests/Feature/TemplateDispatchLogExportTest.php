<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\User;

test('staff can export the dispatch log as pdf with current filters', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Nachweisverein',
        'slug' => 'nachweisverein',
        'email' => 'vorstand@nachweisverein.test',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_STAFF,
        'name' => 'Schriftführung',
    ]);

    TemplateDispatchLog::create([
        'tenant_id' => $tenant->id,
        'created_by' => $staff->id,
        'channel' => 'mail',
        'action' => 'protocol_sent',
        'recipient_type' => 'member',
        'recipient_name' => 'Max Muster',
        'recipient_reference' => 'max@example.test',
        'subject' => 'Protokoll: Vorstandssitzung',
        'message_excerpt' => 'Protokoll wurde per Mail versendet.',
        'open_count' => 0,
        'click_count' => 0,
        'dispatched_at' => now(),
    ]);

    $response = $this->actingAs($staff)->get(route('templates.dispatch-log.pdf', [
        'channel' => 'mail',
        'search' => 'Vorstandssitzung',
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('attachment;');
    expect($response->getContent())->toStartWith('%PDF');
});
