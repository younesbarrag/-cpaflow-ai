<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Admin;
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Admin;
    }

    public function updateRole(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Admin
            && $actor->id !== $target->id;
    }
}
