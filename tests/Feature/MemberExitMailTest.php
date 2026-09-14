<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\MemberCommunicationLog;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('setting a new member exit date sends exit confirmation and logs it on the member', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    Mail::shouldReceive('send')
        ->once()
        ->with(
            'mail.layout',
            Mockery::on(fn (array $data) => str_contains($data['body'], '31.12.2026')
                && str_contains($data['body'], 'Kündigung am 15.09.2026')),
            Mockery::type(Closure::class)
        );

    $tenant = Tenant::create([
        'name' => 'Austrittsverein',
        'slug' => 'austrittsverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
        'member_exit_mail_enabled' => true,
        'member_exit_mail_subject' => 'Austritt bei {verein}',
        'member_exit_mail_body' => '<p>{anrede},</p><p>dein Austritt ist zum {austrittsdatum} vorgemerkt. Kündigung am {kuendigungsdatum}.</p>',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mara',
        'last_name' => 'Mustermann',
        'email' => 'mara@example.test',
        'entry_date' => '2024-01-01',
        'payment_method' => 'ueberweisung',
    ]);

    $this->actingAs($user)->put(route('members.update', $member), [
        'first_name' => 'Mara',
        'last_name' => 'Mustermann',
        'email' => 'mara@example.test',
        'entry_date' => '2024-01-01',
        'termination_date' => '2026-09-15',
        'exit_date' => '2026-12-31',
        'payment_method' => 'ueberweisung',
    ])->assertRedirect(route('members.index'));

    $log = MemberCommunicationLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('member_id', $member->id)
        ->firstOrFail();

    expect($log->channel)->toBe('email')
        ->and($log->direction)->toBe('outgoing')
        ->and($log->recipient)->toBe('mara@example.test')
        ->and($log->subject)->toBe('Austritt bei Austrittsverein')
        ->and($log->message)->toContain('31.12.2026')
        ->and($log->message)->toContain('15.09.2026');

    $dispatchLog = TemplateDispatchLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('recipient_reference', 'mara@example.test')
        ->firstOrFail();

    expect($dispatchLog->action)->toBe('member_exit_confirmation')
        ->and($dispatchLog->recipient_type)->toBe('member')
        ->and($dispatchLog->member_id)->toBe($member->id)
        ->and($dispatchLog->subject)->toBe('Austritt bei Austrittsverein')
        ->and($dispatchLog->message_excerpt)->toContain('31.12.2026')
        ->and($dispatchLog->meta['exit_date'])->toBe('2026-12-31')
        ->and($dispatchLog->meta['termination_date'])->toBe('2026-09-15');
});

test('setting only a termination date does not send an exit confirmation', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    Mail::shouldReceive('send')->never();

    $tenant = Tenant::create([
        'name' => 'Kündigungsverein',
        'slug' => 'kuendigungsverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
        'member_exit_mail_enabled' => true,
        'member_exit_mail_subject' => 'Austritt bei {verein}',
        'member_exit_mail_body' => '<p>Austritt zum {austrittsdatum}</p>',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Tom',
        'last_name' => 'Test',
        'email' => 'tom@example.test',
        'entry_date' => '2024-01-01',
        'payment_method' => 'ueberweisung',
    ]);

    $this->actingAs($user)->put(route('members.update', $member), [
        'first_name' => 'Tom',
        'last_name' => 'Test',
        'email' => 'tom@example.test',
        'entry_date' => '2024-01-01',
        'termination_date' => '2026-09-15',
        'payment_method' => 'ueberweisung',
    ])->assertRedirect(route('members.index'));

    expect(MemberCommunicationLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('member_id', $member->id)
        ->exists())->toBeFalse();
});
