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

    Mail::shouldReceive('send')
        ->once()
        ->with(
            'mail.layout',
            Mockery::on(fn (array $data) => str_contains($data['body'], 'href=')
                && str_contains($data['body'], 'clubano.de%2Fupdate')
                && ! str_contains($data['body'], '{link}')),
            Mockery::type(Closure::class)
        );

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

test('staff can send stored template button links without message link field', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    Mail::shouldReceive('send')
        ->once()
        ->with(
            'mail.layout',
            Mockery::on(fn (array $data) => str_contains($data['body'], 'href=')
                && str_contains($data['body'], 'ghg-sarstedt.de%2Fanmelden')
                && str_contains($data['body'], 'JETZT ANMELDEN')),
            Mockery::type(Closure::class)
        );

    $tenant = Tenant::create([
        'name' => 'Gespeicherter Button Verein',
        'slug' => 'gespeicherter-button-verein',
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
        'name' => 'Gespeicherter Button',
        'subject' => 'Bitte anmelden',
        'body' => '<p><a href="https://ghg-sarstedt.de/anmelden" style="display:inline-block;background:#047857;color:#ffffff;">JETZT ANMELDEN</a></p>',
        'type' => Template::TYPE_MAIL,
    ]);

    $response = $this->actingAs($staff)->post(route('mail.send'), [
        'template_id' => $template->id,
        'subject' => $template->subject,
        'body' => $template->body,
        'members' => [$member->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', '1 Serienmails gesendet');
});

test('template form keeps button link when editor submits anchor without href', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Vorlagenverein',
        'slug' => 'vorlagenverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($staff)->post(route('templates.store'), [
        'name' => 'Einladung',
        'subject' => 'Goldener Oktober',
        'type' => Template::TYPE_MAIL,
        'template_button_url' => 'www.ghg-sarstedt.de/anmelden',
        'body' => '<p><a rel="noopener noreferrer" style="display:inline-block;background:#047857;color:#ffffff;">JETZT ANMELDEN</a></p>',
    ]);

    $response->assertRedirect(route('templates.index'));

    $template = Template::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Einladung')
        ->firstOrFail();

    expect($template->body)
        ->toContain('href="https://www.ghg-sarstedt.de/anmelden"')
        ->toContain('JETZT ANMELDEN');
});

test('staff mail normalizes pasted mojibake before delivery', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    Mail::shouldReceive('send')
        ->once()
        ->with(
            'mail.layout',
            Mockery::on(fn (array $data) => str_contains($data['body'], 'Die GHG lädt')
                && ! str_contains($data['body'], 'lÃ¤dt')),
            Mockery::type(Closure::class)
        );

    $tenant = Tenant::create([
        'name' => 'Umlautverein',
        'slug' => 'umlautverein',
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
        'subject' => 'Update',
        'body' => '<p>Die GHG lÃ¤dt zum Herbstfest ein.</p>',
        'members' => [$member->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', '1 Serienmails gesendet');
});

test('staff mail turns pasted bold cta into clickable button when link is provided', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    Mail::shouldReceive('send')
        ->once()
        ->with(
            'mail.layout',
            Mockery::on(fn (array $data) => str_contains($data['body'], 'Die GHG lädt')
                && str_contains($data['body'], 'Und wir würden')
                && str_contains($data['body'], 'möchten')
                && str_contains($data['body'], 'Grüße')
                && str_contains($data['body'], 'href=')
                && str_contains($data['body'], 'ghg-sarstedt.de%2Fanmelden')
                && ! str_contains($data['body'], 'lÃ¤dt')
                && ! str_contains($data['body'], 'â€“')
                && ! str_contains($data['body'], '**JETZT ANMELDEN**')),
            Mockery::type(Closure::class)
        );

    $tenant = Tenant::create([
        'name' => 'GHG Sarstedt e.V.',
        'slug' => 'ghg-sarstedt',
        'email' => 'kasse@ghg-sarstedt.de',
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

    $body = <<<'HTML'
        <p>am **Sonntag, 25. Oktober 2026, von 12:00 bis 17:00 Uhr** wird es wieder herbstlich und lebendig in der Sarstedter Innenstadt: Die GHG lÃ¤dt zum **Goldenen Oktober mit verkaufsoffenem Sonntag** ein.</p>
        <p>Und wir wÃ¼rden uns freuen, **Sie wieder mit dabei zu haben!**</p>
        <p>Ob mit einem Verkaufsstand oder einer guten Idee â€“ gemeinsam mÃ¶chten wir einen abwechslungsreichen Sonntag schaffen.</p>
        <p style="margin: 24px 0px; text-align: center;"><a rel="noopener noreferrer" style="display: inline-block; background: #047857; color: #ffffff; text-decoration: none; border-radius: 14px; padding: 14px 22px; font-weight: bold;">JETZT ANMELDEN</a></p>
        <p>Herzliche GrÃ¼ÃŸe</p>
    HTML;

    $response = $this->actingAs($staff)->post(route('mail.send'), [
        'subject' => 'Goldener Oktober',
        'body' => $body,
        'message_link' => 'https://ghg-sarstedt.de/anmelden',
        'members' => [$member->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', '1 Serienmails gesendet');
});

test('staff must provide a button link when pasted cta text should be clickable', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'CTA Verein',
        'slug' => 'cta-verein',
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
            'subject' => 'Bitte anmelden',
            'body' => '<p>**JETZT ANMELDEN**</p>',
            'members' => [$member->id],
        ]);

    $response
        ->assertRedirect(route('mail.create'))
        ->assertSessionHasErrors('message_link');
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
