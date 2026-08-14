<?php

namespace App\Services;

final class PostbackSigner
{
    public function tokenFor(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    public function isValid(string $code, string $token): bool
    {
        return hash_equals($this->tokenFor($code), $token);
    }
}
