<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateUserRoleAction
{
    public function execute(User $actor, User $target, string $newRole): User
    {
        return DB::transaction(function () use ($target, $newRole): User {
            $adminUsers = User::query()
                ->where('role', UserRole::Admin)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lockedTarget = User::lockForUpdate()->find($target->id);

            if ($lockedTarget->role->value === $newRole) {
                return $lockedTarget;
            }

            if ($lockedTarget->role === UserRole::Admin && $newRole !== UserRole::Admin->value) {
                if ($adminUsers->count() <= 1) {
                    throw new ConflictHttpException('Cannot demote the last administrator.');
                }
            }

            $lockedTarget->role = $newRole;
            $lockedTarget->save();

            return $lockedTarget;
        });
    }
}
