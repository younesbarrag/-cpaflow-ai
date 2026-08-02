<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Profile Web — Guest Access', function () {
    test('guest is redirected from profile page', function () {
        $response = $this->get(route('profile.edit'));
        $response->assertRedirect(route('login'));
    });
});

describe('Profile Web — Display', function () {
    test('profile page renders', function () {
        $response = $this->actingAs($this->user)->get(route('profile.edit'));
        $response->assertOk();
        $response->assertSee('Profile');
        $response->assertSee('Profile Information');
        $response->assertSee('Update Password');
        $response->assertSee('Delete Account');
    });
});

describe('Profile Web — Update', function () {
    test('profile information can be updated', function () {
        $response = $this->actingAs($this->user)->patch(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'profile-updated');

        $this->user->refresh();
        $this->assertSame('Updated Name', $this->user->name);
        $this->assertSame('updated@example.com', $this->user->email);
    });

    test('profile validation works', function () {
        $response = $this->actingAs($this->user)->patch(route('profile.update'), [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['name', 'email']);
    });
});

describe('Profile Web — Flash Messages', function () {
    test('success status renders after profile update', function () {
        $response = $this->actingAs($this->user)->patch(route('profile.update'), [
            'name' => 'Flash Test',
            'email' => $this->user->email,
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'profile-updated');
    });
});
