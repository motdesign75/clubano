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

        return view('subscription.index');
    }

    public function checkout(Request $request)
{
    abort_unless(auth()->user()?->canManageTenantSettings(), 403);

    $request->validate([
        'price_id' => 'required|string',
    ]);

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
            'success_url' => route('dashboard'),
            'cancel_url' => route('subscription.index'),

            'metadata' => [
                'tenant_id' => $tenant->id, // 🔥 DAS IST DER SCHLÜSSEL
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
}
