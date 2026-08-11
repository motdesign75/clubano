<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Exception\InvalidRequestException;

class SubscriptionController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->canManageTenantSettings(), 403);

        return view('subscription.index', [
            'billingPlans' => $this->billingPlans(),
        ]);
    }

    public function checkout(Request $request)
{
    abort_unless(auth()->user()?->canManageTenantSettings(), 403);

    $request->validate([
        'price_id' => 'required|string',
    ]);

    $billingPlans = $this->billingPlans();
    $selectedPlan = collect($billingPlans)->firstWhere('stripe_price_id', $request->price_id);

    if (! $selectedPlan) {
        abort(403, 'Ungültiger Abo-Plan.');
    }

    $tenant = Auth::user()->tenant;

    if (! $tenant) {
        abort(403);
    }

    $this->ensureStripeCustomer($tenant);

    $subscription = $tenant->newSubscription('default', $request->price_id);

    if ($tenant->onTrial() && $tenant->trial_ends_at) {
        $subscription->trialUntil($tenant->trial_ends_at);
    }

    // 🔥 WICHTIG: Checkout mit Metadata
    return $subscription->checkout([
            'success_url' => route('dashboard', [], true) . '?subscription=success',
            'cancel_url' => route('subscription.index', [], true),
            'payment_method_types' => config('clubano.billing.payment_method_types', ['card', 'sepa_debit']),

            'metadata' => [
                'tenant_id' => $tenant->id, // 🔥 DAS IST DER SCHLÜSSEL
                'billing_plan' => $selectedPlan['key'],
                'price_id' => $selectedPlan['stripe_price_id'],
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'billing_plan' => $selectedPlan['key'],
                    'price_id' => $selectedPlan['stripe_price_id'],
                ],
            ],
        ]);

    }

    private function ensureStripeCustomer($tenant): void
    {
        try {
            $tenant->createOrGetStripeCustomer();
        } catch (InvalidRequestException $e) {
            if (!str_contains($e->getMessage(), 'No such customer')) {
                throw $e;
            }

            $tenant->forceFill(['stripe_id' => null])->save();
            $tenant->createOrGetStripeCustomer();
        }
    }

    private function billingPlans(): array
    {
        return collect(config('clubano.billing.plans', []))
            ->filter(fn (array $plan) => filled($plan['stripe_price_id'] ?? null))
            ->values()
            ->all();
    }
}
