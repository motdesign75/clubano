<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Exception\InvalidRequestException;

class BillingController extends Controller
{
    /**
     * Zeigt die verfügbaren Abo-Modelle (Starter/Basic/Enterprise)
     * basierend auf config('clubano.plans') mit stripe_price_ids[].
     */
    public function plans(Request $request)
    {
        $tenant = $request->user()?->tenant;

        if (!$tenant) {
            abort(403, 'Kein Tenant gefunden.');
        }

        $plans = config('clubano.plans') ?? [];

        // Nur Pläne anzeigen, die mindestens eine Price-ID haben
        $plans = array_filter($plans, function ($p) {
            $ids = $p['stripe_price_ids'] ?? [];
            return is_array($ids) && count($ids) > 0;
        });

        return view('billing.plans', compact('tenant', 'plans'));
    }

    /**
     * Startet den Checkout für den übergebenen Stripe Price (price_...)
     * Route: POST /billing/subscribe/{priceId}
     */
    public function subscribe(Request $request, string $priceId)
    {
        $tenant = $request->user()->tenant;

        if (!$tenant) {
            abort(403, 'Kein Tenant gefunden.');
        }

        // Sicherheitscheck: nur Price-IDs zulassen, die in config('clubano.plans') definiert sind
        $allowedPriceIds = collect(config('clubano.plans') ?? [])
            ->flatMap(fn ($p) => $p['stripe_price_ids'] ?? [])
            ->filter()
            ->values();

        if ($allowedPriceIds->isNotEmpty() && !$allowedPriceIds->contains($priceId)) {
            abort(403, 'Ungültiger Plan.');
        }

        // Wenn bereits aktiv: direkt ins Portal
        if ($tenant->subscribed('default')) {
            return $tenant->redirectToBillingPortal(route('dashboard', [], true));
        }

        $this->ensureStripeCustomer($tenant);

        $subscription = $tenant->newSubscription('default', $priceId);

        if ($tenant->onTrial() && $tenant->trial_ends_at) {
            $subscription->trialUntil($tenant->trial_ends_at);
        }

        return $subscription->checkout([
                'success_url' => route('dashboard', [], true) . '?subscription=success',
                'cancel_url'  => route('dashboard', [], true) . '?subscription=cancelled',
            ]);
    }

    /**
     * Öffnet das Stripe Billing Portal (Abo verwalten)
     */
    public function portal(Request $request)
    {
        $tenant = $request->user()->tenant;

        if (!$tenant || !$tenant->stripe_id) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein Stripe-Konto für diesen Verein gefunden.');
        }

        try {
            return $tenant->redirectToBillingPortal(route('dashboard', [], true));
        } catch (InvalidRequestException $e) {
            if (!str_contains($e->getMessage(), 'No such customer')) {
                throw $e;
            }

            $tenant->forceFill(['stripe_id' => null])->save();

            return redirect()->route('billing.plans')
                ->with('error', 'Das hinterlegte Stripe-Konto war nicht mehr gültig. Bitte starte den Checkout erneut.');
        }
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
