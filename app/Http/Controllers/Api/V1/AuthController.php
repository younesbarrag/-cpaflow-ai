<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\AuthenticateApiUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginApiRequest;
use App\Http\Requests\Api\V1\Auth\RegisterApiRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginApiRequest $request, AuthenticateApiUserAction $action): JsonResponse
    {
        $deviceName = $request->validated('device_name', 'api-client');

        $result = $action->execute(
            $request->validated('email'),
            $request->validated('password'),
            $deviceName,
        );

        if ($result->throttled) {
            return response()->json([
                'message' => 'Too many login attempts. Please try again later.',
            ], 429, [
                'Retry-After' => $result->retryAfter,
            ]);
        }

        if (! $result->success) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'user' => new UserResource($result->user),
            ],
            'token' => $result->token,
            'token_type' => 'Bearer',
        ]);
    }

    public function register(RegisterApiRequest $request, RegisterUserAction $action): JsonResponse
    {
        $user = $action->execute(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
        );

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $request->user()->tokens()->where('id', $token->id)->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($request->user()),
            ],
        ]);
    }
}
