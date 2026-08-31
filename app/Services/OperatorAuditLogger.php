<?php

namespace App\Services;

use App\Models\OperatorAuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OperatorAuditLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(Request $request, string $action, ?string $label = null, ?Tenant $tenant = null, array $metadata = []): OperatorAuditLog
    {
        $actor = $request->user();

        return OperatorAuditLog::create([
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'target_tenant_id' => $tenant?->id,
            'target_tenant_name' => $tenant?->name,
            'target_tenant_email' => $tenant?->email,
            'action' => $action,
            'label' => $label,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'metadata' => $this->redact($metadata),
            'created_at' => now(),
        ]);
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[geschützt]';
        }

        if (is_array($value)) {
            return Arr::map($value, fn ($item, $itemKey) => $this->redact($item, is_string($itemKey) ? $itemKey : null));
        }

        if (is_string($value)) {
            return Str::limit($value, 500, '...');
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = Str::lower($key);

        foreach (['password', 'passwort', 'token', 'secret', 'key', 'iban', 'bic', 'mail_password', 'stripe', 'fingerprint'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
