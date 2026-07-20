<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantRole
{
    public function handle(Request $request, Closure $next, string $requiredRole): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasRoleAtLeast($requiredRole)) {
            abort(403, 'Deine Rolle reicht fuer diesen Bereich nicht aus.');
        }

        return $next($request);
    }
}
