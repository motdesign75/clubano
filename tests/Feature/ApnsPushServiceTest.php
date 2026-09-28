<?php

use App\Services\ApnsPushService;

test('apns jwt signature is encoded as verifiable jose signature', function () {
    $privateKeyPem = <<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIOmmBjmOTffmWQyto7v3Ahl0io613xTmoA576irvOHCHoAoGCCqGSM49
AwEHoUQDQgAEUDn5IAx+iPs6iuzhC1wSv+alcJEdz0IYcnqW6ZcbkIwY78Qwu9Az
1Yyb7DGCXhuukZjj1t7WV94K4VMapixvZA==
-----END EC PRIVATE KEY-----
PEM;
    $privateKey = openssl_pkey_get_private($privateKeyPem);
    $publicKey = openssl_pkey_get_details($privateKey)['key'];

    config([
        'services.apns.key_id' => 'TESTKEY123',
        'services.apns.team_id' => 'TEAM123456',
        'services.apns.bundle_id' => 'de.clubano.app',
        'services.apns.private_key' => $privateKeyPem,
        'services.apns.private_key_path' => null,
    ]);

    $service = app(ApnsPushService::class);
    $reflection = new ReflectionClass($service);
    $jwt = $reflection->getMethod('jwt')->invoke($service);

    [$header, $payload, $signature] = explode('.', $jwt);
    $rawSignature = base64_decode(strtr($signature, '-_', '+/'));

    $r = substr($rawSignature, 0, 32);
    $s = substr($rawSignature, 32, 32);
    $derSignature = derSignature($r, $s);

    expect(strlen($rawSignature))->toBe(64)
        ->and(openssl_verify($header . '.' . $payload, $derSignature, $publicKey, OPENSSL_ALGO_SHA256))->toBe(1);
});

function derSignature(string $r, string $s): string
{
    $sequence = derInteger($r) . derInteger($s);

    return "\x30" . chr(strlen($sequence)) . $sequence;
}

function derInteger(string $value): string
{
    $value = ltrim($value, "\x00");

    if ($value === '') {
        $value = "\x00";
    }

    if (ord($value[0]) > 0x7f) {
        $value = "\x00" . $value;
    }

    return "\x02" . chr(strlen($value)) . $value;
}
