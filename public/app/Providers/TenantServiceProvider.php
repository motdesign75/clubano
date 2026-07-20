<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Tenant;
use Illuminate\Support\Facades\View;

class TenantServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('currentTenant', function () {
            $user = auth()->user();
            return $user ? Tenant::find($user->tenant_id) : null;
        });
    }

    public function boot()
    {
        // Optional: Tenant global für alle Views verfügbar machen
        if (auth()->check()) {
            View::share('tenant', app('currentTenant'));
        }
    }
}
