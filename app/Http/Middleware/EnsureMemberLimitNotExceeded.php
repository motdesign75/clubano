<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\LicenseService;
use App\Models\Member;

class EnsureMemberLimitNotExceeded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        if (!$user || !$tenant) {
            return $next($request);
        }

        // Kein Abo? -> hier nicht blocken (wenn du "ohne Abo kein Arbeiten" willst,
        // machen wir zusätzlich eine EnsureTenantSubscribed Middleware)
        if ($tenant->hasComplimentaryAccess() || !$tenant->subscribed('default')) {
            return $next($request);
        }

        $license = app(LicenseService::class)->resolvePlan($tenant);
        $limit = $license['member_limit'] ?? null;

        // Unbegrenzt
        if ($limit === null) {
            return $next($request);
        }

        $limit = (int) $limit;

        // Mitglieder zählen (Scope greift automatisch auf aktuellen Tenant)
        // Fallback zusätzlich tenant_id filtern (falls Scope irgendwann deaktiviert wird)
        $membersCount = Member::query()
            ->where('tenant_id', $user->tenant_id)
            ->count();

        if ($membersCount >= $limit) {
            return redirect()
                ->route('dashboard')
                ->with('error', "Mitgliederlimit erreicht ({$membersCount} / {$limit}). Bitte upgraden, um weitere Mitglieder anzulegen.");
        }

        return $next($request);
    }
}
