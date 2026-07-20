<?php

namespace App\DTOs;

use App\Models\User;

class LoginResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?User $user = null,
        public readonly ?string $token = null,
        public readonly bool $throttled = false,
        public readonly ?int $retryAfter = null,
    ) {}

    public static function success(User $user, string $token): self
    {
        return new self(success: true, user: $user, token: $token);
    }

    public static function failed(): self
    {
        return new self(success: false);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(success: false, throttled: true, retryAfter: $retryAfter);
    }
}
