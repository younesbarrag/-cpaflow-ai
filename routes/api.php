<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->name('api.v1.auth.login');

    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:api-register')
        ->name('api.v1.auth.register');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('api.v1.auth.logout');

        Route::get('/auth/user', [AuthController::class, 'user'])
            ->name('api.v1.auth.user');

        Route::patch('/profile', [ProfileController::class, 'update'])
            ->name('api.v1.profile.update');
    });
});
