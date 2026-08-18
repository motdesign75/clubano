<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('finance user can configure voucher design and download voucher pdf', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('public');

    $tenant = Tenant::create([
        'name' => 'Gutscheinverein',
        'slug' => 'gutscheinverein',
        'email' => 'vorstand@gutscheinverein.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    $settingsResponse = $this->actingAs($user)->put(route('vouchers.settings.update'), [
        'voucher_template' => UploadedFile::fake()->image('braugutschein.png', 1600, 900),
        'voucher_code_position' => 'bottom-left',
        'voucher_code_color' => '#123456',
        'voucher_show_qr' => '1',
        'voucher_mail_subject' => 'Dein Gutschein',
        'voucher_mail_body' => '<p>Gutschein {{ code }} über {{ wert }}</p>',
    ]);

    $settingsResponse->assertRedirect(route('vouchers.settings'));

    $tenant->refresh();

    expect($tenant->voucher_template_path)->not->toBeNull();
    expect($tenant->voucher_template_width)->toBe(1600);
    expect($tenant->voucher_template_height)->toBe(900);
    Storage::disk('public')->assertExists($tenant->voucher_template_path);

    $voucher = Voucher::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'code' => 'CLB-2026-TEST01',
        'title' => 'Kursgutschein',
        'original_amount' => 79,
        'remaining_amount' => 79,
        'currency' => 'EUR',
        'issued_at' => now()->toDateString(),
        'status' => Voucher::STATUS_ACTIVE,
        'recipient_name' => 'Max Muster',
        'recipient_email' => 'max@example.test',
        'delivery_method' => Voucher::DELIVERY_MAIL,
        'created_by' => $user->id,
    ]);

    $downloadResponse = $this->actingAs($user)->get(route('vouchers.download', $voucher));

    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('content-type', 'application/pdf');
    expect($downloadResponse->getContent())->toStartWith('%PDF');

    $this->actingAs($user)
        ->get(route('vouchers.check', ['code' => $voucher->code]))
        ->assertOk()
        ->assertSee('Gültig')
        ->assertSee($voucher->code);

    $this->actingAs($user)
        ->get(route('vouchers.check', ['code' => 'FALSCH']))
        ->assertOk()
        ->assertSee('Nicht gefunden');
});

test('voucher creation stores delivery method without sending automatically', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenant = Tenant::create([
        'name' => 'Abholverein',
        'slug' => 'abholverein',
        'email' => 'vorstand@abholverein.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('vouchers.store'), [
        'title' => 'Braukurs',
        'original_amount' => '79.00',
        'issued_at' => now()->toDateString(),
        'buyer_name' => 'Erika Einkauf',
        'buyer_email' => 'erika@example.test',
        'recipient_name' => 'Max Muster',
        'recipient_email' => 'max@example.test',
        'delivery_method' => Voucher::DELIVERY_PICKUP,
    ]);

    $response->assertRedirect(route('vouchers.index'));

    $voucher = Voucher::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->delivery_method)->toBe(Voucher::DELIVERY_PICKUP);
    expect($voucher->delivered_at)->toBeNull();
});
