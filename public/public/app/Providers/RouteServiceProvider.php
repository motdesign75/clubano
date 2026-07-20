<?php

namespace App\Providers;

use App\Models\Transaction;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Der Pfad zur "Home"-Route für deine Anwendung.
     */
    public const HOME = '/dashboard';

    public function boot(): void
    {
        // Sicheres Binding für Transactions: Nur für eingeloggte Nutzer und deren Tenant
        Route::bind('transaction', function ($value) {
            $user = Auth::user();

            // Falls kein eingeloggter Nutzer vorhanden ist, abbrechen
            if (!$user) {
                abort(403, 'Nicht autorisiert.');
            }

            // Versuche, die Transaction tenant-beschränkt zu laden
            return Transaction::where('id', $value)
                ->where('tenant_id', $user->tenant_id)
                ->firstOrFail();
        });

        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        });
    }
}
