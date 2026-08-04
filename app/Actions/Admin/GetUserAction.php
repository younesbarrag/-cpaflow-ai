<?php

namespace App\Actions\Admin;

use App\Models\User;

final class GetUserAction
{
    public function execute(int $userId): User
    {
        return User::findOrFail($userId);
    }
}
