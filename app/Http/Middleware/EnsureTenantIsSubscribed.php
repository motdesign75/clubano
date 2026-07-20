<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || ! $user->tenant) {
            return redirect()->route('login');
        }

        $tenant = $user->tenant;

        if ($tenant->subscribed('default') || $tenant->onTrial() || $tenant->hasComplimentaryAccess()) {
            return $next($request);
        }

        return redirect()
            ->route('subscription.index')
            ->with('error', 'Bitte aktivieren Sie zunächst Ihre Lizenz.');
    }
}
