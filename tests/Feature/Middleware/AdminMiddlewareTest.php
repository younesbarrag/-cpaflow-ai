<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::prefix('api')->middleware(['auth:sanctum', 'admin'])->get('/test-admin', fn () => response()->json(['ok' => true]));
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/test-admin');

    $response->assertStatus(401);
});

test('authenticated affiliate returns 403', function () {
    $user = User::factory()->create();
    $user->role = UserRole::Affiliate;
    $user->save();
    $token = $user->createToken('test-device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/test-admin');

    $response->assertStatus(403)
        ->assertJson(['message' => 'Forbidden.']);
});

test('authenticated admin passes', function () {
    $user = User::factory()->create();
    $user->role = UserRole::Admin;
    $user->save();
    $token = $user->createToken('test-device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/test-admin');

    $response->assertOk()
        ->assertJson(['ok' => true]);
});

test('middleware uses userrole enum comparison', function () {
    $admin = User::factory()->create();
    $admin->role = UserRole::Admin;
    $admin->save();

    $affiliate = User::factory()->create();
    $affiliate->role = UserRole::Affiliate;
    $affiliate->save();

    $adminToken = $admin->createToken('admin-device')->plainTextToken;
    $affiliateToken = $affiliate->createToken('affiliate-device')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$affiliateToken)
        ->getJson('/api/test-admin')
        ->assertStatus(403);

    Auth::forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/test-admin')
        ->assertOk();
});
