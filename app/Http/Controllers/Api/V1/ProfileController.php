<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profile\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateUserProfileAction $action): JsonResponse
    {
        $user = $action->execute(
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
        );

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
