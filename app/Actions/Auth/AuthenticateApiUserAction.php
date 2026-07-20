<?php

namespace App\Actions\Auth;

use App\DTOs\LoginResult;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Hash;

class AuthenticateApiUserAction
{
    public function __construct(
        private RateLimiter $rateLimiter,
    ) {}

    public function execute(string $email, string $password, string $deviceName): LoginResult
    {
        $normalizedEmail = strtolower(trim($email));
        $ip = request()->ip();
        $throttleKey = 'login:'.$normalizedEmail.'|'.$ip;

        if ($this->rateLimiter->tooManyAttempts($throttleKey, 5)) {
            $availableIn = $this->rateLimiter->availableIn($throttleKey);

            return LoginResult::throttled($availableIn);
        }

        $user = User::where('email', $normalizedEmail)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->rateLimiter->increment($throttleKey, 60);

            return LoginResult::failed();
        }

        $this->rateLimiter->clear($throttleKey);

        $token = $user->createToken($deviceName)->plainTextToken;

        return LoginResult::success($user, $token);
    }
}
