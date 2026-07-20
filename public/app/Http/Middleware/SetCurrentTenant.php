<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Setze den aktuellen Tenant global
            app()->instance('currentTenantId', Auth::user()->tenant_id);
        }

        return $next($request);
    }
}
