<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ✅ Stripe Webhooks dürfen nicht durch CSRF laufen (sonst 419)
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'stripe/*',
        ]);

        // ✅ Middleware-Aliases
        $middleware->alias([
            'member.limit' => \App\Http\Middleware\EnsureMemberLimitNotExceeded::class,
            'no-cache' => \App\Http\Middleware\PreventPageCache::class,

            // 🔥 NEU: Paywall Middleware
            'tenant.subscribed' => \App\Http\Middleware\EnsureTenantIsSubscribed::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'tenant.role' => \App\Http\Middleware\EnsureTenantRole::class,
            'demo.protect' => \App\Http\Middleware\ProtectDemoMode::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ProtectDemoMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $exception, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Die Seite ist abgelaufen. Bitte versuche es erneut.',
                ], 419);
            }

            return redirect()
                ->back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'Deine Sitzung war abgelaufen. Bitte versuche es noch einmal.');
        });
    })->create();
