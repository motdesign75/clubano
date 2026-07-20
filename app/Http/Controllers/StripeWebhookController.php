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
                    $trialDays = (int) config('clubano.trial_days', 14);

                    DB::table('subscriptions')->insert([
                        'tenant_id' => $tenant->id,
                        'name' => 'default',
                        'stripe_id' => $session['subscription'] ?? null,
                        'stripe_status' => 'active',
                        'stripe_price' => null,
                        'quantity' => 1,
                        'trial_ends_at' => now()->addDays($trialDays),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
