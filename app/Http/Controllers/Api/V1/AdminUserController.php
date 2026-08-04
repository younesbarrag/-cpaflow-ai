<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\GetUserAction;
use App\Actions\Admin\ListUsersAction;
use App\Actions\Admin\UpdateUserRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateUserRoleRequest;
use App\Http\Resources\Api\V1\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminUserController extends Controller
{
    public function index(
        ListUsersAction $action,
    ): AnonymousResourceCollection {
        $paginator = $action->execute(
            search: request('search'),
            role: request('role'),
        );

        return AdminUserResource::collection($paginator);
    }

    public function show(
        int $user,
        GetUserAction $action,
    ): JsonResponse {
        $user = $action->execute($user);

        return response()->json([
            'data' => new AdminUserResource($user),
        ]);
    }

    public function update(
        UpdateUserRoleRequest $request,
        User $user,
        UpdateUserRoleAction $action,
    ): JsonResponse {
        $actor = auth()->user();

        $updated = $action->execute($actor, $user, $request->validated('role'));

        return response()->json([
            'data' => new AdminUserResource($updated),
        ]);
    }
}
