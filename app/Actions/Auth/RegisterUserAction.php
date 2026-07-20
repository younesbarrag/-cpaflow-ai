<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;

class RegisterUserAction
{
    public function execute(string $name, string $email, string $password): User
    {
        $user = new User;
        $user->fill([
            'name' => $name,
            'email' => strtolower(trim($email)),
            'password' => $password,
        ]);
        $user->role = UserRole::Affiliate;
        $user->save();

        return $user;
    }
}
