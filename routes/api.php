<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TrackingLinkController;
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

        Route::get('/offers', [OfferController::class, 'index'])
            ->name('api.v1.offers.index');

        Route::post('/offers', [OfferController::class, 'store'])
            ->name('api.v1.offers.store');
        Route::patch('/offers/{offer}', [OfferController::class, 'update'])
            ->name('api.v1.offers.update');

        Route::post('/offers/{offer}/archive', [OfferController::class, 'archive'])
            ->name('api.v1.offers.archive');

        Route::get('/campaigns', [CampaignController::class, 'index'])
            ->name('api.v1.campaigns.index');

        Route::post('/campaigns', [CampaignController::class, 'store'])
            ->name('api.v1.campaigns.store');

        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])
            ->name('api.v1.campaigns.show');

        Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update'])
            ->name('api.v1.campaigns.update');

        Route::post('/campaigns/{campaign}/activate', [CampaignController::class, 'activate'])
            ->name('api.v1.campaigns.activate');

        Route::post('/campaigns/{campaign}/suspend', [CampaignController::class, 'suspend'])
            ->name('api.v1.campaigns.suspend');

        Route::post(
            '/campaigns/{campaign}/tracking-links',
            [TrackingLinkController::class, 'store']
        )->name('api.v1.campaigns.tracking-links.store');

    });

});
