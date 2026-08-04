<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUsersAction
{
    public function execute(?string $search = null, ?string $role = null): LengthAwarePaginator
    {
        $query = User::query();

        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($role !== null) {
            $query->where('role', UserRole::from($role));
        }

        return $query->orderBy('id')->paginate(15);
    }
}
