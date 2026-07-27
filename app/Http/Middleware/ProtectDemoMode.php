<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectDemoMode
{
    /**
     * @var array<int, string>
     */
    private array $blockedRoutePatterns = [
        'billing.*',
        'subscription.*',
        'users.*',
        'tenant.update',
        'settings.email.*',
        'roles.*',
        'admin.*',
        'mail.send',
        'letters.generate',
        'protocols.mail.send',
        'events.invitations.mail',
        'invoices.send',
        'invoices.reminder',
        'sepa.export',
        'sepa.download',
        'members.datenauskunft',
        'members.pdf',
        'profile.update',
        'profile.destroy',
        'password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        if (! $tenant?->isDemo()) {
            return $next($request);
        }

        if ($request->isMethodSafe() || $request->routeIs('logout') || $request->routeIs('feedback.store')) {
            return $next($request);
        }

        if ($request->isMethod('delete') || $this->routeIsBlocked($request)) {
            return $this->blockedResponse($request);
        }

        return $next($request);
    }

    private function routeIsBlocked(Request $request): bool
    {
        foreach ($this->blockedRoutePatterns as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function blockedResponse(Request $request): Response
    {
        $message = 'Demo-Modus: Diese Aktion ist gesperrt, damit keine echten Mails, Exporte, Zahlungen, Benutzer- oder Vereinsdaten verändert werden.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 423);
        }

        return redirect()
            ->back()
            ->withInput($request->except(['password', 'password_confirmation']))
            ->with('warning', $message);
    }
}
