<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Mail\AutomatedClubMail;
use App\Models\AutomatedMailDelivery;
use App\Models\AutomatedMailSetting;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

test('staff can configure birthday automation with formatted content and images', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Geburtstagsverein',
        'slug' => 'geburtstagsverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($staff)
        ->get(route('automated-mails.index'))
        ->assertOk()
        ->assertSee('Automatische Mails')
        ->assertSee('Geburtstag');

    $response = $this->actingAs($staff)->put(route('automated-mails.update', 'birthday'), [
        'enabled' => '1',
        'subject' => 'Alles Gute, {{ vorname }}',
        'body_html' => '<p><strong>Liebe/r {{ vorname }}</strong></p><p><img src="data:image/png;base64,aGVsbG8=" alt="Bild"></p>',
        'days_before' => 0,
        'send_time' => '09:00',
    ]);

    $response->assertRedirect(route('automated-mails.index'));

    $setting = AutomatedMailSetting::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('occasion', 'birthday')
        ->firstOrFail();

    expect($setting->enabled)->toBeTrue()
        ->and($setting->body_html)->toContain('<strong>')
        ->and($setting->body_html)->toContain('data:image/png;base64');
});

test('birthday automation sends once and ignores archived members', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 09:30:00'));
    Mail::fake();

    $tenant = Tenant::create([
        'name' => 'Automatikverein',
        'slug' => 'automatikverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
    ]);

    $setting = AutomatedMailSetting::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'occasion' => AutomatedMailSetting::OCCASION_BIRTHDAY,
        'enabled' => true,
        'subject' => 'Herzlichen Glückwunsch, {{ vorname }}',
        'body_html' => '<p><strong>Hallo {{ vorname }}</strong>, du wirst {{ alter }}.</p>',
        'days_before' => 0,
        'send_time' => '09:00',
    ]);

    $activeMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mara',
        'last_name' => 'Mustermann',
        'email' => 'mara@example.test',
        'birthday' => '1980-09-09',
        'entry_date' => '2020-01-01',
        'consent_email' => true,
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Archiv',
        'last_name' => 'Person',
        'email' => 'archiv@example.test',
        'birthday' => '1980-09-09',
        'entry_date' => '2020-01-01',
        'archived_at' => now(),
        'consent_email' => true,
    ]);

    $this->artisan('clubano:send-automated-mails', ['--tenant' => $tenant->id])->assertExitCode(0);
    $this->artisan('clubano:send-automated-mails', ['--tenant' => $tenant->id])->assertExitCode(0);

    Mail::assertSent(AutomatedClubMail::class, function (AutomatedClubMail $mail) use ($activeMember) {
        return $mail->hasTo($activeMember->email)
            && str_contains($mail->mailSubject, 'Mara')
            && str_contains($mail->bodyHtml, '<strong>Hallo Mara</strong>')
            && str_contains($mail->bodyHtml, '46');
    });
    Mail::assertSentCount(1);

    expect(AutomatedMailDelivery::withoutGlobalScopes()
        ->where('automated_mail_setting_id', $setting->id)
        ->where('member_id', $activeMember->id)
        ->count())->toBe(1);

    Carbon::setTestNow();
});
