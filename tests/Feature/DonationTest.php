<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Document;
use App\Models\Donation;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function createDonationUser(bool $withFreistellung = true): array
{
    $tenant = Tenant::create([
        'name' => 'Spendenverein',
        'slug' => 'spendenverein',
        'email' => 'verein@example.test',
        'address' => 'Vereinsweg 1',
        'zip' => '12345',
        'city' => 'Demostadt',
        'license_mode' => 'gifted',
        'donation_certificates_enabled' => true,
        'donation_tax_office' => 'Finanzamt Demostadt',
        'donation_tax_number' => '12/345/67890',
        'donation_notice_authority' => 'Finanzamt Demostadt',
        'donation_notice_date' => now()->subYear()->toDateString(),
        'donation_purposes' => 'Förderung des Sports.',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    if ($withFreistellung) {
        $document = Document::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $user->id,
            'title' => 'Freistellungsbescheid',
            'category' => Document::CATEGORY_CLUB,
            'status' => Document::STATUS_ACTIVE,
            'description' => 'Testnachweis',
            'tags' => ['Gemeinnützigkeit'],
            'document_date' => now()->subYear()->toDateString(),
            'expires_at' => now()->addYears(4)->toDateString(),
            'disk' => 'local',
            'path' => 'test/freistellung.pdf',
            'original_name' => 'freistellung.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $tenant->forceFill(['donation_freistellung_document_id' => $document->id])->save();
    }

    return [$tenant, $user];
}

test('admins can create a donation from a member and create a finance transaction', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    [$tenant, $user] = createDonationUser();

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Cathrin',
        'last_name' => 'Homann',
        'email' => 'spender@example.test',
        'street' => 'Demoweg 5',
        'zip' => '12345',
        'city' => 'Demostadt',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
        'active' => true,
        'balance_start' => 0,
        'balance_current' => 0,
    ]);

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'number' => '4200',
        'name' => 'Spenden',
        'type' => 'einnahme',
        'active' => true,
        'balance_start' => 0,
        'balance_current' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('donations.store'), [
        'member_id' => $member->id,
        'fill_from_member' => '1',
        'donated_at' => now()->toDateString(),
        'amount' => '125.50',
        'purpose' => 'Jugendarbeit',
        'payment_method' => 'ueberweisung',
        'create_transaction' => '1',
        'cash_account_id' => $bank->id,
        'income_account_id' => $income->id,
    ]);

    $donation = Donation::withoutGlobalScopes()->first();

    $response->assertRedirect(route('donations.show', $donation));
    expect($donation->donor_name)->toBe('Cathrin Homann');
    expect($donation->transaction_id)->not->toBeNull();
    expect(Transaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('admins can open donation overview create form and settings', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    [, $user] = createDonationUser();

    $this->actingAs($user)->get(route('donations.index'))->assertOk()->assertSee('Zuwendungen');
    $this->actingAs($user)->get(route('donations.create'))->assertOk()->assertSee('Spende erfassen');
    $this->actingAs($user)->get(route('donations.settings'))->assertOk()->assertSee('Zuwendungsbestätigung');
});

test('pdf creation issues a certificate number', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    [$tenant, $user] = createDonationUser();

    $donation = Donation::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'status' => Donation::STATUS_DRAFT,
        'kind' => 'money',
        'donated_at' => now()->toDateString(),
        'amount' => 50,
        'purpose' => 'Vereinsarbeit',
        'donor_name' => 'Max Demo',
        'donor_zip' => '12345',
        'donor_city' => 'Demostadt',
    ]);

    $response = $this->actingAs($user)->get(route('donations.pdf', $donation));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($donation->fresh()->certificate_number)->toStartWith('SP-' . now()->year . '-');
});

test('pdf creation is blocked without freistellungsbescheid', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    [$tenant, $user] = createDonationUser(false);

    $donation = Donation::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'status' => Donation::STATUS_DRAFT,
        'kind' => 'money',
        'donated_at' => now()->toDateString(),
        'amount' => 50,
        'purpose' => 'Vereinsarbeit',
        'donor_name' => 'Max Demo',
        'donor_zip' => '12345',
        'donor_city' => 'Demostadt',
    ]);

    $response = $this->actingAs($user)->get(route('donations.pdf', $donation));

    $response->assertRedirect(route('donations.show', $donation));
    $response->assertSessionHas('error');
    expect($donation->fresh()->certificate_number)->toBeNull();
});

test('settings upload stores freistellungsbescheid as club document', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    [$tenant, $user] = createDonationUser(false);

    $response = $this->actingAs($user)->put(route('donations.settings.update'), [
        'donation_certificates_enabled' => '1',
        'donation_tax_office' => 'Finanzamt Demostadt',
        'donation_tax_number' => '12/345/67890',
        'donation_notice_authority' => 'Finanzamt Demostadt',
        'donation_notice_date' => now()->subYear()->toDateString(),
        'donation_notice_valid_until' => now()->addYears(4)->toDateString(),
        'donation_purposes' => 'Förderung des Sports.',
        'freistellung_document' => UploadedFile::fake()->create('freistellung.pdf', 20, 'application/pdf'),
    ]);

    $response->assertRedirect(route('donations.settings'));
    $tenant->refresh();

    expect($tenant->donation_freistellung_document_id)->not->toBeNull();
    expect($tenant->load('donationFreistellungDocument')->canIssueDonationCertificates())->toBeTrue();
    expect($tenant->donationFreistellungDocument->category)->toBe(Document::CATEGORY_CLUB);
});
