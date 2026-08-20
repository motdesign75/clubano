<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;

class TenantMailConfigurator
{
    public function apply(?Tenant $tenant): void
    {
        if (!$tenant || blank($tenant->mail_host)) {
            return;
        }

        $port = filled($tenant->mail_port)
            ? (int) $tenant->mail_port
            : (int) config('mail.mailers.smtp.port', 587);

        $encryption = filled($tenant->mail_encryption)
            ? $tenant->mail_encryption
            : config('mail.mailers.smtp.encryption');

        if (blank($encryption)) {
            $encryption = match ($port) {
                465 => 'ssl',
                587 => 'tls',
                default => null,
            };
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', $tenant->mail_mailer ?? 'smtp');
        Config::set('mail.mailers.smtp.host', $tenant->mail_host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $tenant->mail_username);
        Config::set('mail.mailers.smtp.password', $tenant->mail_password);
        Config::set('mail.mailers.smtp.encryption', $encryption);
        Config::set('mail.from.address', $tenant->mail_from_address ?? 'noreply@clubano.de');
        Config::set('mail.from.name', $tenant->mail_from_name ?? 'Clubano');
    }
}
