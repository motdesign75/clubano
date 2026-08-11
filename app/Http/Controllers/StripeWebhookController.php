<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event = $request->all();

        if ($event['type'] === 'checkout.session.completed') {

            $session = $event['data']['object'];

            $tenantId = $session['metadata']['tenant_id'] ?? null;

            if ($tenantId) {

                $tenant = Tenant::find($tenantId);

                if ($tenant) {
                    $subscriptionId = $session['subscription'] ?? null;

                    if (! $subscriptionId) {
                        return response()->json(['status' => 'ignored']);
                    }

                    $priceId = $session['metadata']['price_id'] ?? null;
                    $billingPlan = $session['metadata']['billing_plan'] ?? null;

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
