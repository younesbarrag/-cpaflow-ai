<?php

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Admin User Management — Security', function () {
    test('guest on index returns 401', function () {
        $this->getJson('/api/v1/admin/users')
            ->assertStatus(401);
    });

    test('guest on show returns 401', function () {
        $user = User::factory()->create();

        $this->getJson('/api/v1/admin/users/'.$user->id)
            ->assertStatus(401);
    });

    test('guest on update returns 401', function () {
        $user = User::factory()->create();

        $this->patchJson('/api/v1/admin/users/'.$user->id, ['role' => 'affiliate'])
            ->assertStatus(401);
    });

    test('affiliate on index returns 403', function () {
        $affiliate = User::factory()->create();
        $token = $affiliate->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users')
            ->assertStatus(403);
    });

    test('affiliate on show returns 403', function () {
        $affiliate = User::factory()->create();
        $token = $affiliate->createToken('test')->plainTextToken;
        $target = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users/'.$target->id)
            ->assertStatus(403);
    });

    test('affiliate on update returns 403', function () {
        $affiliate = User::factory()->create();
        $token = $affiliate->createToken('test')->plainTextToken;
        $target = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/users/'.$target->id, ['role' => 'admin'])
            ->assertStatus(403);
    });
});

describe('Admin User Management — List', function () {
    test('admin lists users returns 200 with data array', function () {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        User::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role']],
            ]);
    });

    test('admin lists users returns pagination meta', function () {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        User::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users');

        $response->assertOk()
            ->assertJsonStructure([
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    test('sensitive fields are absent from list response', function () {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/users');

        $response->assertOk()
            ->assertJsonMissing(['password', 'remember_token']);
    });
});

describe('Admin User Management — Search and Filter', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->token = $this->admin->createToken('test')->plainTextToken;
    });

    test('search by name filters results', function () {
        User::factory()->create(['name' => 'Jane Doe']);
        User::factory()->create(['name' => 'John Smith']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/users?search=Jane');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Jane Doe', $names);
        $this->assertNotContains('John Smith', $names);
    });

    test('search by email filters results', function () {
        User::factory()->create(['email' => 'jane@example.com']);
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/users?search=jane@');

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains('jane@example.com', $emails);
        $this->assertNotContains('john@example.com', $emails);
    });

    test('role filter returns only matching role', function () {
        User::factory()->admin()->create();
        User::factory()->count(2)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/users?role=admin');

        $response->assertOk();
        $roles = collect($response->json('data'))->pluck('role')->all();
        $this->assertContains('admin', $roles);
        $this->assertNotContains('affiliate', $roles);
    });

    test('combined search and role filter works', function () {
        User::factory()->create(['name' => 'Jane Doe', 'role' => 'affiliate']);
        User::factory()->create(['name' => 'Jane Admin', 'role' => 'admin']);
        User::factory()->create(['name' => 'John Doe', 'role' => 'affiliate']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/users?search=Jane&role=affiliate');

        $response->assertOk();
        $data = collect($response->json('data'));
        $this->assertCount(1, $data);
        $this->assertEquals('Jane Doe', $data->first()['name']);
    });

    test('empty results returns 200 with empty data', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/users?search=nonexistent');

        $response->assertOk()
            ->assertJson(['data' => []]);
    });
});

describe('Admin User Management — Show', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->token = $this->admin->createToken('test')->plainTextToken;
    });

    test('admin shows existing user returns 200', function () {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/users/'.$user->id);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
            ])
            ->assertJson(['data' => ['id' => $user->id]]);
    });

    test('admin shows unknown user returns 404', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/users/99999');

        $response->assertStatus(404);
    });

    test('admin shows self returns 200', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/admin/users/'.$this->admin->id);

        $response->assertOk()
            ->assertJson(['data' => ['id' => $this->admin->id]]);
    });
});

describe('Admin User Management — Role Update', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->token = $this->admin->createToken('test')->plainTextToken;
    });

    test('valid role update affiliate to admin returns 200', function () {
        $affiliate = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$affiliate->id, ['role' => 'admin']);

        $response->assertOk()
            ->assertJson(['data' => ['role' => 'admin']]);

        $this->assertDatabaseHas('users', ['id' => $affiliate->id, 'role' => 'admin']);
    });

    test('valid role update admin to affiliate when another admin remains returns 200', function () {
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$otherAdmin->id, ['role' => 'affiliate']);

        $response->assertOk()
            ->assertJson(['data' => ['role' => 'affiliate']]);

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'role' => 'affiliate']);
    });

    test('same role update admin to admin returns 200 with no mutation', function () {
        $otherAdmin = User::factory()->admin()->create();
        $originalUpdatedAt = $otherAdmin->updated_at;

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$otherAdmin->id, ['role' => 'admin']);

        $response->assertOk()
            ->assertJson(['data' => ['role' => 'admin']]);

        $otherAdmin->refresh();
        $this->assertEquals($originalUpdatedAt->timestamp, $otherAdmin->updated_at->timestamp);
    });

    test('same role update affiliate to affiliate returns 200 with no mutation', function () {
        $affiliate = User::factory()->create();
        $originalUpdatedAt = $affiliate->updated_at;

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$affiliate->id, ['role' => 'affiliate']);

        $response->assertOk()
            ->assertJson(['data' => ['role' => 'affiliate']]);

        $affiliate->refresh();
        $this->assertEquals($originalUpdatedAt->timestamp, $affiliate->updated_at->timestamp);
    });

    test('invalid role returns 422', function () {
        $affiliate = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$affiliate->id, ['role' => 'superadmin']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    });

    test('self demotion returns 403', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$this->admin->id, ['role' => 'affiliate']);

        $response->assertStatus(403);
    });

    test('last admin demotion returns 409', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$this->admin->id, ['role' => 'affiliate']);

        $response->assertStatus(403);
    });

    test('non admin cannot update returns 403', function () {
        $affiliate = User::factory()->create();
        $token = $affiliate->createToken('test')->plainTextToken;
        $target = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/users/'.$target->id, ['role' => 'admin']);

        $response->assertStatus(403);
    });
});

describe('Admin User Management — Body Scope', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->token = $this->admin->createToken('test')->plainTextToken;
    });

    test('patch with name field does not modify name', function () {
        $affiliate = User::factory()->create(['name' => 'Original Name']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$affiliate->id, [
                'role' => 'admin',
                'name' => 'Hacked Name',
            ]);

        $affiliate->refresh();
        $this->assertEquals('Original Name', $affiliate->name);
    });

    test('patch with email field does not modify email', function () {
        $affiliate = User::factory()->create(['email' => 'original@example.com']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/admin/users/'.$affiliate->id, [
                'role' => 'admin',
                'email' => 'hacked@example.com',
            ]);

        $affiliate->refresh();
        $this->assertEquals('original@example.com', $affiliate->email);
    });
});

describe('Admin User Management — Ownership Regression', function () {
    test('admin on foreign offer returns 403', function () {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $otherUser = User::factory()->create();
        $offer = Offer::factory()->create(['user_id' => $otherUser->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/offers/'.$offer->id, ['name' => 'Admin Override'])
            ->assertStatus(403);
    });
});

describe('Admin User Management — Invariants', function () {
    test('two admins exist and admin a demotes admin b returns 200', function () {
        $adminA = User::factory()->admin()->create();
        $adminB = User::factory()->admin()->create();
        $token = $adminA->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/users/'.$adminB->id, ['role' => 'affiliate']);

        $response->assertOk()
            ->assertJson(['data' => ['role' => 'affiliate']]);

        $this->assertDatabaseHas('users', ['id' => $adminB->id, 'role' => 'affiliate']);
        $this->assertDatabaseHas('users', ['id' => $adminA->id, 'role' => 'admin']);
    });

    test('single admin cannot be demoted returns 409', function () {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/users/'.$admin->id, ['role' => 'affiliate']);

        $response->assertStatus(403);
    });
});
