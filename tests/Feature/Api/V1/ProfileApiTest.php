<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('*');
});

describe('PATCH /api/v1/profile', function () {
    test('unauthenticated request returns 401', function () {
        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(401);
    });

    test('authenticated user updates name', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'user@example.com',
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'email' => 'user@example.com',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                ],
            ])
            ->assertJson([
                'data' => [
                    'user' => [
                        'name' => 'Updated Name',
                        'email' => 'user@example.com',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    });

    test('authenticated user updates email', function () {
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => $user->name,
                'email' => 'new@example.com',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
        ]);
    });

    test('current unchanged email is accepted', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'email' => 'user@example.com',
            ]);

        $response->assertOk();
    });

    test('email whitespace is trimmed and lowercased', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Test User',
                'email' => '  User@Example.COM  ',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'user@example.com',
        ]);
    });

    test('case-variant duplicate email is rejected', function () {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Test User',
                'email' => 'TAKEN@EXAMPLE.COM',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('another user email returns 422', function () {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Test User',
                'email' => 'taken@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('invalid email returns 422', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Test User',
                'email' => 'not-an-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('missing name returns 422', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'email' => 'user@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('name longer than 255 returns 422', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => str_repeat('a', 256),
                'email' => 'user@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('role=admin input does not change affiliate role', function () {
        $user = User::factory()->create();
        $user->role = UserRole::Affiliate;
        $user->save();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Test User',
                'email' => 'user@example.com',
                'role' => 'admin',
            ]);

        $response->assertOk();

        $user->refresh();
        $this->assertEquals(UserRole::Affiliate, $user->role);
    });

    test('password remains unchanged after profile update', function () {
        $user = User::factory()->create(['password' => 'password123']);
        $originalPassword = $user->password;
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'email' => 'user@example.com',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertEquals($originalPassword, $user->password);
    });

    test('current sanctum token remains valid after update', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'email' => 'user@example.com',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/user')
            ->assertOk();
    });

    test('response uses userresource and excludes sensitive fields', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'email' => 'user@example.com',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                ],
            ])
            ->assertJsonMissing(['password', 'remember_token']);
    });

    test('changing only the name preserves email_verified_at', function () {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'email' => 'user@example.com',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    });

    test('changing the email resets email_verified_at to null', function () {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/profile', [
                'name' => $user->name,
                'email' => 'new@example.com',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    });
});
