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

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', $tenant->mail_mailer ?? 'smtp');
        Config::set('mail.mailers.smtp.host', $tenant->mail_host);
        Config::set('mail.mailers.smtp.port', $tenant->mail_port);
        Config::set('mail.mailers.smtp.username', $tenant->mail_username);
        Config::set('mail.mailers.smtp.password', $tenant->mail_password);
        Config::set('mail.mailers.smtp.encryption', $tenant->mail_encryption);
        Config::set('mail.from.address', $tenant->mail_from_address ?? 'noreply@clubano.de');
        Config::set('mail.from.name', $tenant->mail_from_name ?? 'Clubano');
    }
}
