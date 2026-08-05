<?php

use App\Exceptions\CannotGenerateTrackingLink;
use App\Exceptions\DuplicateConversionException;
use App\Exceptions\InvalidCampaignTransition;
use App\Exceptions\InvalidConversionTransition;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            InvalidCampaignTransition::class,
            InvalidConversionTransition::class,
        ]);

        $exceptions->render(
            function (
                InvalidCampaignTransition $exception,
                Request $request,
            ): ?JsonResponse {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'message' => 'The campaign transition is not allowed.',
                    'errors' => [
                        'status' => [
                            $exception->getMessage(),
                        ],
                    ],
                ], 409);
            },
        );

        $exceptions->render(
            function (
                InvalidConversionTransition $exception,
                Request $request,
            ): ?JsonResponse {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'message' => 'The conversion transition is not allowed.',
                    'errors' => [
                        'status' => [
                            $exception->getMessage(),
                        ],
                    ],
                ], 409);
            },
        );

        $exceptions->render(
            function (
                CannotGenerateTrackingLink $exception,
                Request $request,
            ): ?JsonResponse {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'message' => 'The tracking link could not be generated.',
                ], 500);
            },
        );

        $exceptions->render(
            function (
                DuplicateConversionException $exception,
                Request $request,
            ): ?JsonResponse {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'message' => 'A conversion with this external ID already exists.',
                    'errors' => [
                        'external_id' => [
                            $exception->getMessage(),
                        ],
                    ],
                ], 409);
            },
        );
    })
    ->create();
