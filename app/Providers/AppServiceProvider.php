<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Login::class, function (Login $event) {
            $event->user?->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => request()?->ip(),
            ])->save();
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Bitte bestätige deine E-Mail-Adresse')
                ->greeting('Willkommen bei Clubano!')
                ->line('bitte bestätige deine E-Mail-Adresse, damit wir sicher wissen, dass die Registrierung zu einem echten Verein gehört.')
                ->action('E-Mail-Adresse bestätigen', $url)
                ->line('Erst danach kannst du Clubano vollständig nutzen.')
                ->line('Falls du diese Registrierung nicht selbst vorgenommen hast, musst du nichts weiter tun.');
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $email = $notifiable->getEmailForPasswordReset();
            $tenantName = $notifiable->tenant?->name;
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], false));

            return (new MailMessage)
                ->subject('Dein Zugang zu Clubano')
                ->greeting('Willkommen bei Clubano!')
                ->line($tenantName
                    ? "du wurdest eingeladen, in Clubano für {$tenantName} mitzuarbeiten."
                    : 'du kannst jetzt deinen Clubano-Zugang einrichten.')
                ->line('Lege über den folgenden Button dein persönliches Passwort fest. Danach kannst du dich direkt anmelden.')
                ->action('Passwort festlegen', $url)
                ->line('Der Link ist aus Sicherheitsgründen zeitlich begrenzt.')
                ->line('Falls du diese Nachricht nicht erwartet hast, kannst du sie einfach ignorieren.');
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
