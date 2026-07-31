<?php

namespace App\Services\TrackingLink;

class IpHasher
{
    private string $hashingKey;

    public function __construct(?string $key = null)
    {
        $resolvedKey = $key ?? (string) config('app.key');

        $this->hashingKey = hash_hmac(
            'sha256',
            'tracking-ip-hash:v1',
            $resolvedKey,
            true,
        );
    }

    public function hash(?string $ip): ?string
    {
        $normalized = $this->normalize($ip);

        if ($normalized === null) {
            return null;
        }

        return hash_hmac('sha256', $normalized, $this->hashingKey);
    }

    private function normalize(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        $ip = trim($ip);

        if ($ip === '') {
            return null;
        }

        $ip = preg_replace('/%[a-zA-Z0-9]+$/', '', $ip);

        $packed = @inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        $normalized = inet_ntop($packed);

        if ($normalized === false) {
            return null;
        }

        return $normalized;
    }
}
