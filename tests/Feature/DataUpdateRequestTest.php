<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Mail\AutomatedClubMail;
use App\Models\Contact;
use App\Models\DataUpdateRequest;
use App\Models\Member;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

test('staff can request member data updates and approve submitted changes', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    $tenant = Tenant::create([
        'name' => 'Datenverein',
        'slug' => 'datenverein',
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
        'mobile' => '0170 123',
        'street' => 'Alte Strasse 1',
        'zip' => '31157',
        'city' => 'Sarstedt',
        'country' => 'Deutschland',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $response = $this->actingAs($staff)->post(route('data-update-requests.store'), [
        'target_type' => 'member',
        'member_ids' => [$member->id],
        'message' => 'Bitte kurz pruefen.',
    ]);

    $response->assertRedirect(route('data-update-requests.index'));
    Mail::assertSent(AutomatedClubMail::class);

    $dataRequest = DataUpdateRequest::query()->firstOrFail();

    expect($dataRequest->status)->toBe(DataUpdateRequest::STATUS_SENT)
        ->and($dataRequest->member_id)->toBe($member->id)
        ->and(TemplateDispatchLog::query()->where('action', 'data_update_request_sent')->exists())->toBeTrue();

    $submitUrl = URL::temporarySignedRoute(
        'data-update-requests.public.submit',
        now()->addDays(30),
        ['token' => $dataRequest->token],
    );

    $this->post($submitUrl, [
        'salutation' => '',
        'title' => '',
        'first_name' => 'Mara',
        'last_name' => 'Mustermann',
        'organization' => '',
        'email' => 'mara.neu@example.test',
        'mobile' => '0171 999',
        'landline' => '',
        'street' => 'Neue Strasse 2',
        'address_addition' => '',
        'zip' => '31157',
        'city' => 'Sarstedt',
        'country' => 'Deutschland',
        'change_note' => 'E-Mail und Handy sind neu.',
    ])->assertOk();

    $dataRequest->refresh();
    $member->refresh();

    expect($dataRequest->status)->toBe(DataUpdateRequest::STATUS_SUBMITTED)
        ->and($dataRequest->changes)->toHaveKeys(['email', 'mobile', 'street'])
        ->and($member->email)->toBe('mara@example.test')
        ->and($member->mobile)->toBe('0170 123');

    $this->actingAs($staff)
        ->post(route('data-update-requests.approve', $dataRequest))
        ->assertRedirect(route('data-update-requests.index'));

    $member->refresh();

    expect($member->email)->toBe('mara.neu@example.test')
        ->and($member->mobile)->toBe('0171 999')
        ->and($member->street)->toBe('Neue Strasse 2')
        ->and($dataRequest->fresh()->status)->toBe(DataUpdateRequest::STATUS_APPROVED);
});

test('staff can request contact data updates without applying them before review', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Mail::fake();

    $tenant = Tenant::create([
        'name' => 'Kontaktverein',
        'slug' => 'kontaktverein',
        'email' => 'verein@example.test',
        'license_mode' => 'gifted',
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $contact = Contact::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'organization' => 'Beispiel GmbH',
        'first_name' => 'Kai',
        'last_name' => 'Kontakt',
        'email' => 'kontakt@example.test',
        'phone' => '05066 1',
        'street' => 'Markt 1',
        'zip' => '31157',
        'city' => 'Sarstedt',
        'country' => 'Deutschland',
        'is_active' => true,
    ]);

    $this->actingAs($staff)->post(route('data-update-requests.store'), [
        'target_type' => 'contact',
        'contact_ids' => [$contact->id],
    ])->assertRedirect(route('data-update-requests.index'));

    $dataRequest = DataUpdateRequest::query()->where('contact_id', $contact->id)->firstOrFail();
    $submitUrl = URL::temporarySignedRoute(
        'data-update-requests.public.submit',
        now()->addDays(30),
        ['token' => $dataRequest->token],
    );

    $this->post($submitUrl, [
        'organization' => 'Beispiel GmbH',
        'department' => '',
        'position' => '',
        'salutation' => '',
        'title' => '',
        'first_name' => 'Kai',
        'last_name' => 'Kontakt',
        'email' => 'kontakt@example.test',
        'secondary_email' => 'office@example.test',
        'mobile' => '',
        'phone' => '05066 2',
        'street' => 'Markt 1',
        'address_addition' => '',
        'zip' => '31157',
        'city' => 'Sarstedt',
        'country' => 'Deutschland',
    ])->assertOk();

    $contact->refresh();

    expect($contact->phone)->toBe('05066 1')
        ->and($dataRequest->fresh()->changes)->toHaveKeys(['secondary_email', 'phone']);

    $this->actingAs($staff)
        ->post(route('data-update-requests.reject', $dataRequest))
        ->assertRedirect(route('data-update-requests.index'));

    expect($contact->fresh()->phone)->toBe('05066 1')
        ->and($dataRequest->fresh()->status)->toBe(DataUpdateRequest::STATUS_REJECTED);
});
