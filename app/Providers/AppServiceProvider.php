<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use App\Http\Livewire\DashboardMemberStats;
use Laravel\Cashier\Cashier;
use App\Models\Tenant;
use App\Services\TenantMailConfigurator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Bitte bestätige deine E-Mail-Adresse')
                ->greeting('Willkommen bei Clubano!')
                ->line('bitte bestätige deine E-Mail-Adresse, damit wir sicher wissen, dass die Registrierung zu einem echten Verein gehört.')
                ->action('E-Mail-Adresse bestätigen', $url)
                ->line('Erst danach kannst du Clubano vollständig nutzen.')
                ->line('Falls du diese Registrierung nicht selbst vorgenommen hast, musst du nichts weiter tun.');
        });

        /**
         * 🔥 WICHTIG:
         * Cashier auf Tenant umstellen (Multi-Tenant Billing)
         */
        Cashier::useCustomerModel(Tenant::class);

        /**
         * Livewire-Komponente registrieren
         */
        Livewire::component('dashboard-member-stats', DashboardMemberStats::class);

        /**
         * Dynamische SMTP-Konfiguration pro Tenant
         */
        if (Auth::check()) {
            $tenant = Auth::user()->tenant ?? null;

            if ($tenant && $tenant->mail_host) {
                app(TenantMailConfigurator::class)->apply($tenant);
            }
        }
    }
}
