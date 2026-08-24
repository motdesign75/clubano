<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('staff can send direct html mail with attachments without a template', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Kommunikationsverein',
        'slug' => 'kommunikationsverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mara',
        'last_name' => 'Mustermann',
        'email' => 'mara@example.test',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $response = $this->actingAs($staff)->post(route('mail.send'), [
        'subject' => 'Hallo {vorname}',
        'body' => '<h2>Hallo {vorname}</h2><p>Direkt geschrieben.</p>',
        'members' => [$member->id],
        'attachments' => [
            UploadedFile::fake()->create('info.pdf', 64, 'application/pdf'),
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', '1 Serienmails gesendet');

    $log = TemplateDispatchLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('recipient_reference', 'mara@example.test')
        ->firstOrFail();

    expect($log->template_id)->toBeNull()
        ->and($log->subject)->toBe('Hallo Mara')
        ->and($log->message_excerpt)->toContain('Hallo Mara')
        ->and($log->meta['composition_mode'])->toBe('direct')
        ->and($log->meta['attachment_count'])->toBe(1)
        ->and($log->meta['attachment_names'])->toBe(['info.pdf']);
});
