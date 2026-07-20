<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Member;
use App\Models\Tenant;

class GermanIbanBicResolver
{
    public function resolve(?string $iban, int|string|null $tenantId = null): ?array
    {
        $normalizedIban = $this->normalizeIban($iban);
        $bankCode = $this->extractGermanBankCode($normalizedIban);

        if (! $bankCode) {
            return null;
        }

        $configBic = config("bank_codes_bic.{$bankCode}");
        if (filled($configBic)) {
            return [
                'bic' => $this->normalizeBic($configBic),
                'source' => 'config',
                'source_label' => 'bekannter Bankcode',
                'bank_code' => $bankCode,
            ];
        }

        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant && filled($tenant->iban) && filled($tenant->bic) && $this->extractGermanBankCode($tenant->iban) === $bankCode) {
            return [
                'bic' => $this->normalizeBic($tenant->bic),
                'source' => 'tenant',
                'source_label' => 'Vereinskonto',
                'bank_code' => $bankCode,
            ];
        }

        $account = Account::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('iban')
            ->whereNotNull('bic')
            ->get()
            ->first(fn ($item) => $this->extractGermanBankCode($item->iban) === $bankCode);

        if ($account) {
            return [
                'bic' => $this->normalizeBic($account->bic),
                'source' => 'account',
                'source_label' => 'hinterlegtes Konto',
                'bank_code' => $bankCode,
            ];
        }

        $member = Member::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('iban')
            ->whereNotNull('bic')
            ->get()
            ->first(fn ($item) => $this->extractGermanBankCode($item->iban) === $bankCode);

        if ($member) {
            return [
                'bic' => $this->normalizeBic($member->bic),
                'source' => 'member',
                'source_label' => 'bereits bekanntes Mitglied',
                'bank_code' => $bankCode,
            ];
        }

        return null;
    }

    public function normalizeIban(?string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $iban));
    }

    public function normalizeBic(?string $bic): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $bic));
    }

    public function extractGermanBankCode(?string $iban): ?string
    {
        $normalizedIban = $this->normalizeIban($iban);

        if (! str_starts_with($normalizedIban, 'DE') || strlen($normalizedIban) < 12) {
            return null;
        }

        $bankCode = substr($normalizedIban, 4, 8);

        return preg_match('/^\d{8}$/', $bankCode) ? $bankCode : null;
    }
}
