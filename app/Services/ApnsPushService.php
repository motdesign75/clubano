<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ApnsPushService
{
    public function configured(): bool
    {
        return filled(config('services.apns.key_id'))
            && filled(config('services.apns.team_id'))
            && filled(config('services.apns.bundle_id'))
            && filled($this->privateKey());
    }

    public function send(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        if (! $this->configured()) {
            Log::warning('APNs push skipped because credentials are missing');

            return false;
        }

        $environment = config('services.apns.environment') === 'development' ? 'sandbox' : 'production';
        $host = $environment === 'sandbox' ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';

        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'sound' => 'default',
            ],
            'data' => $data,
        ];

        try {
            $response = $this->postHttp2("{$host}/3/device/{$deviceToken}", $payload);

            if ($response['status'] >= 200 && $response['status'] < 300) {
                return true;
            }

            Log::warning('APNs push delivery failed', [
                'status' => $response['status'],
                'body' => $response['body'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('APNs push delivery failed', [
                'message' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    private function postHttp2(string $url, array $payload): array
    {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_HTTPHEADER => [
                'authorization: bearer ' . $this->jwt(),
                'apns-topic: ' . config('services.apns.bundle_id'),
                'apns-push-type: alert',
                'apns-priority: 10',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = curl_exec($curl);

        if ($body === false) {
            $message = curl_error($curl);
            curl_close($curl);

            throw new \RuntimeException($message);
        }

        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    private function jwt(): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => config('services.apns.key_id'),
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => config('services.apns.team_id'),
            'iat' => time(),
        ], JSON_THROW_ON_ERROR));

        $privateKey = openssl_pkey_get_private($this->privateKey());

        if (! $privateKey) {
            throw new \RuntimeException('APNs private key could not be loaded.');
        }

        openssl_sign("{$header}.{$payload}", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$header}.{$payload}." . $this->base64UrlEncode($this->derToJose($signature));
    }

    private function privateKey(): ?string
    {
        $inline = config('services.apns.private_key');

        if (filled($inline)) {
            return str_replace('\\n', "\n", (string) $inline);
        }

        $path = config('services.apns.private_key_path');

        if (! filled($path) || ! is_readable($path)) {
            return null;
        }

        return file_get_contents($path) ?: null;
    }

    private function derToJose(string $signature): string
    {
        $offset = 0;

        if (ord($signature[$offset++]) !== 0x30) {
            throw new \RuntimeException('Invalid APNs signature sequence.');
        }

        $this->readLength($signature, $offset);

        if (ord($signature[$offset++]) !== 0x02) {
            throw new \RuntimeException('Invalid APNs signature R value.');
        }

        $r = substr($signature, $offset, $this->readLength($signature, $offset));
        $offset += strlen($r);

        if (ord($signature[$offset++]) !== 0x02) {
            throw new \RuntimeException('Invalid APNs signature S value.');
        }

        $s = substr($signature, $offset, $this->readLength($signature, $offset));

        return $this->normalizeInteger($r) . $this->normalizeInteger($s);
    }

    private function readLength(string $input, int &$offset): int
    {
        $length = ord($input[$offset++]);

        if ($length < 0x80) {
            return $length;
        }

        $bytes = $length & 0x7f;
        $length = 0;

        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | ord($input[$offset++]);
        }

        return $length;
    }

    private function normalizeInteger(string $value): string
    {
        $value = ltrim($value, "\x00");

        return str_pad($value, 32, "\x00", STR_PAD_LEFT);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
