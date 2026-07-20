<?php

namespace App\Actions\Profile;

use App\Models\User;

class UpdateUserProfileAction
{
    public function execute(
        User $user,
        string $name,
        string $email,
    ): User {
        $normalizedEmail = strtolower(trim($email));

        if ($user->email !== $normalizedEmail) {
            $user->email_verified_at = null;
        }

        $user->fill([
            'name' => $name,
            'email' => $normalizedEmail,
        ]);

        $user->save();

        return $user;
    }
}
