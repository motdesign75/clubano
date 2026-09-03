<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

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

test('staff can fill template button link for clickable mail buttons', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    $tenant = Tenant::create([
        'name' => 'Buttonverein',
        'slug' => 'buttonverein',
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

    $template = Template::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Button Vorlage',
        'subject' => 'Bitte klicken',
        'body' => '<p><a href="{link}" style="display:inline-block;background:#2954A3;color:#ffffff;">Jetzt oeffnen</a></p>',
        'type' => Template::TYPE_MAIL,
    ]);

    $response = $this->actingAs($staff)->post(route('mail.send'), [
        'template_id' => $template->id,
        'subject' => $template->subject,
        'body' => $template->body,
        'message_link' => 'https://clubano.de/update',
        'members' => [$member->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', '1 Serienmails gesendet');

    $log = TemplateDispatchLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('recipient_reference', 'mara@example.test')
        ->firstOrFail();

    expect($log->message_excerpt)->toContain('Jetzt oeffnen')
        ->and($log->meta['composition_mode'])->toBe('template_applied');
});

test('staff must provide a button link when template contains link placeholder', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Pflichtlinkverein',
        'slug' => 'pflichtlinkverein',
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

    $response = $this->actingAs($staff)
        ->from(route('mail.create'))
        ->post(route('mail.send'), [
            'subject' => 'Bitte klicken',
            'body' => '<p><a href="{link}">Jetzt oeffnen</a></p>',
            'members' => [$member->id],
        ]);

    $response
        ->assertRedirect(route('mail.create'))
        ->assertSessionHasErrors('message_link');
});
