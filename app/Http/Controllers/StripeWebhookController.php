<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (blank($webhookSecret)) {
            Log::critical('Stripe webhook secret is not configured.');

            return response()->json(['message' => 'Webhook not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Stripe webhook rejected.', [
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        if ($event->type === 'checkout.session.completed') {

            $session = $event->data->object;

            $tenantId = $session->metadata->tenant_id ?? null;

            if ($tenantId) {

                $tenant = Tenant::find($tenantId);

                if ($tenant) {
                    $subscriptionId = $session->subscription ?? null;

                    if (! $subscriptionId) {
                        return response()->json(['status' => 'ignored']);
                    }

                    $priceId = $session->metadata->price_id ?? null;
                    $billingPlan = $session->metadata->billing_plan ?? null;

                    if (! $priceId && $billingPlan) {
                        $priceId = config("clubano.billing.plans.{$billingPlan}.stripe_price_id");
                    }

                    $trialEndsAt = $tenant->trial_ends_at && $tenant->trial_ends_at->isFuture()
                        ? $tenant->trial_ends_at
                        : null;

                    DB::table('subscriptions')->updateOrInsert(
                        [
                            'stripe_id' => $subscriptionId,
                        ],
                        [
                            'tenant_id' => $tenant->id,
                            'name' => 'default',
                            'stripe_status' => 'active',
                            'stripe_price' => $priceId,
                            'quantity' => 1,
                            'trial_ends_at' => $trialEndsAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
