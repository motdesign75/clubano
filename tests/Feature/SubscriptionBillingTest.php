<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function createBillingTenantWithAdmin(): array
{
    $suffix = Str::lower(Str::random(6));

    $tenant = Tenant::create([
        'name' => 'Billing Verein ' . $suffix,
        'slug' => 'billing-verein-' . $suffix,
        'email' => 'billing-' . $suffix . '@example.test',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $admin];
}

test('license page offers monthly and yearly stripe plans with sepa hint', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $admin] = createBillingTenantWithAdmin();

    $this->actingAs($admin)
        ->get(route('subscription.index'))
        ->assertOk()
        ->assertSee('Monatlich flexibel')
        ->assertSee('19,99 €')
        ->assertSee('Jährlich sparen')
        ->assertSee('199,00 €')
        ->assertSee('SEPA-Lastschrift')
        ->assertSee('price_1U3A51LTnGBaGb0lp3MVF2it');
});

test('checkout rejects unknown stripe price ids before contacting stripe', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $admin] = createBillingTenantWithAdmin();

    $this->actingAs($admin)
        ->post(route('subscription.checkout'), [
            'price_id' => 'price_unknown',
        ])
        ->assertForbidden();
});

test('stripe checkout webhook stores the selected yearly price id', function () {
    [$tenant] = createBillingTenantWithAdmin();

    $this->post(route('stripe.webhook'), [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'subscription' => 'sub_yearly_test',
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'billing_plan' => 'yearly',
                    'price_id' => 'price_1U3A51LTnGBaGb0lp3MVF2it',
                ],
            ],
        ],
    ])->assertOk();

    $subscription = DB::table('subscriptions')
        ->where('stripe_id', 'sub_yearly_test')
        ->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->tenant_id)->toBe($tenant->id)
        ->and($subscription->stripe_price)->toBe('price_1U3A51LTnGBaGb0lp3MVF2it')
        ->and($subscription->stripe_status)->toBe('active');
});
