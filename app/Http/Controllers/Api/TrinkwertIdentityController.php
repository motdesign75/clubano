<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrinkwertIdentityController extends Controller
{
    public function resolveMember(Request $request)
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'qr_payload' => ['required_without:payload', 'string', 'max:1000'],
            'payload' => ['required_without:qr_payload', 'string', 'max:1000'],
        ]);

        $payload = trim((string) ($validated['qr_payload'] ?? $validated['payload'] ?? ''));
        $parsed = $this->parsePayload($payload);

        if (! $parsed) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_payload',
                'message' => 'Der QR-Code ist kein gültiger Clubano-Mitgliederausweis.',
            ], 422);
        }

        $member = Member::withoutGlobalScopes()
            ->with('tenant')
            ->where('mobile_identity_uuid', $parsed['member_uuid'])
            ->first();

        if (! $member || ! $member->tenant) {
            return $this->invalidIdentity('unknown_member');
        }

        $tenantPublicId = (string) ($member->tenant->invite_code ?: $member->tenant_id);

        if (! hash_equals($tenantPublicId, $parsed['tenant_public_id'])) {
            return $this->invalidIdentity('tenant_mismatch');
        }

        if ($member->mobile_identity_revoked_at || ! $member->mobile_identity_secret) {
            return $this->invalidIdentity('revoked_identity');
        }

        if ($member->archived_at || ($member->exit_date && $member->exit_date->isPast())) {
            return $this->invalidIdentity('inactive_member');
        }

        if (! hash_equals($this->signatureFor($parsed, $member), $parsed['signature'])) {
            return $this->invalidIdentity('invalid_signature');
        }

        return response()->json([
            'valid' => true,
            'tenant' => [
                'id' => $member->tenant->id,
                'public_id' => $tenantPublicId,
                'name' => $member->tenant->name,
            ],
            'member' => [
                'identity_uuid' => $member->mobile_identity_uuid,
                'member_number' => $member->member_id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'full_name' => $member->full_name,
                'status' => $member->status,
            ],
        ]);
    }

    private function ensureAuthorized(Request $request): void
    {
        $token = (string) config('services.trinkwert.integration_token');
        $provided = (string) $request->bearerToken();

        abort_if($token === '', 503, 'Die Trinkwert-Integration ist noch nicht konfiguriert.');
        abort_unless($provided !== '' && hash_equals($token, $provided), 401, 'Nicht autorisiert.');
    }

    private function parsePayload(string $payload): ?array
    {
        if (! Str::startsWith($payload, 'clubano://member/v1/')) {
            return null;
        }

        $parts = explode('/', Str::after($payload, 'clubano://member/v1/'));

        if (count($parts) !== 3) {
            return null;
        }

        [$tenantPublicId, $memberUuid, $signature] = $parts;

        if (! Str::isUuid($memberUuid) || ! preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            return null;
        }

        return [
            'tenant_public_id' => $tenantPublicId,
            'member_uuid' => $memberUuid,
            'signature' => strtolower($signature),
        ];
    }

    private function signatureFor(array $parsed, Member $member): string
    {
        $payload = implode('|', [
            'clubano-member',
            'v1',
            $parsed['tenant_public_id'],
            $parsed['member_uuid'],
        ]);

        return hash_hmac('sha256', $payload, config('app.key') . '|' . $member->mobile_identity_secret);
    }

    private function invalidIdentity(string $reason)
    {
        return response()->json([
            'valid' => false,
            'reason' => $reason,
            'message' => 'Die Clubano-Mitgliedsidentität konnte nicht bestätigt werden.',
        ], 404);
    }
}
