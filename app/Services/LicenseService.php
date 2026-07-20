<?php

namespace App\Services;

use App\Models\Tenant;

class LicenseService
{
    public function resolvePlan(Tenant $tenant): array
    {
        if ($tenant->hasComplimentaryAccess()) {
            return [
                'key' => $tenant->license_mode,
                'name' => $tenant->license_mode === 'beta' ? 'Pilotlizenz' : 'Freilizenz',
                'member_limit' => null,
                'stripe_price' => null,
            ];
        }

        $subscription = $tenant->subscription('default');
        $stripePrice  = $subscription?->stripe_price;

        $plans = config('clubano.plans', []);

        $planKey = null;
        $plan = null;

        if ($stripePrice) {
            foreach ($plans as $key => $p) {
                $ids = $p['stripe_price_ids'] ?? [];
                if (in_array($stripePrice, $ids, true)) {
                    $planKey = $key;
                    $plan = $p;
                    break;
                }
            }
        }

        return [
            'key' => $planKey,
            'name' => $tenant->subscribed('default')
                ? ($plan['name'] ?? 'Aktiv (unbekannter Plan)')
                : 'Keine aktiv',
            'member_limit' => $tenant->subscribed('default')
                ? ($plan['member_limit'] ?? null)
                : null,
            'stripe_price' => $stripePrice,
        ];
    }

    public function utilization(int $currentMembers, ?int $limit): array
    {
        if ($limit === null || $limit <= 0) {
            return [
                'percent' => null,
                'near' => false,
                'over' => false,
            ];
        }

        $percent = (int) round(($currentMembers / $limit) * 100);
        $warnThreshold = (int) config('clubano.warn_threshold_percent', 95);

        return [
            'percent' => $percent,
            'near' => $percent >= $warnThreshold,
            'over' => $currentMembers >= $limit,
        ];
    }
}
