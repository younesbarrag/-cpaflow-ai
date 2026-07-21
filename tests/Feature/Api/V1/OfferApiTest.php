<?php

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test-device')->plainTextToken;
});

describe('POST /api/v1/offers — Authentication', function () {
    test('guest cannot create an offer', function () {
        $response = $this->postJson('/api/v1/offers', [
            'name' => 'Test Offer',
            'destination_url' => 'https://example.com',
            'payout' => '10.00',
            'status' => 'draft',
        ]);

        $response->assertStatus(401);
    });
});

describe('GET /api/v1/offers — Authentication', function () {
    test('guest cannot list offers', function () {
        $response = $this->getJson('/api/v1/offers');

        $response->assertStatus(401);
    });
});

describe('POST /api/v1/offers — Successful creation', function () {
    test('authenticated user creates an offer', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Fitness Trial',
                'destination_url' => 'https://example.com/offer',
                'payout' => '25.50',
                'status' => 'draft',
                'description' => 'Optional description',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'offer' => [
                        'id',
                        'name',
                        'destination_url',
                        'payout',
                        'status',
                        'description',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'offer' => [
                        'name' => 'Fitness Trial',
                        'destination_url' => 'https://example.com/offer',
                        'payout' => '25.50',
                        'status' => 'draft',
                        'description' => 'Optional description',
                    ],
                ],
            ]);
    });

    test('offer belongs to authenticated user', function () {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $this->assertDatabaseHas('offers', [
            'user_id' => $this->user->id,
            'name' => 'Test Offer',
        ]);
    });

    test('response does not expose user_id', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(201)
            ->assertJsonMissing(['user_id']);
    });

    test('default status persists correctly', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $offer = Offer::where('user_id', $this->user->id)->first();
        $this->assertEquals(OfferStatus::Draft, $offer->status);
    });

    test('description may be null', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('offers', [
            'user_id' => $this->user->id,
            'description' => null,
        ]);
    });
});

describe('POST /api/v1/offers — Ownership security', function () {
    test('public user_id is ignored', function () {
        $otherUser = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
                'user_id' => $otherUser->id,
            ]);

        $this->assertDatabaseHas('offers', [
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseMissing('offers', [
            'user_id' => $otherUser->id,
        ]);
    });

    test('public owner_id is ignored', function () {
        $otherUser = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
                'owner_id' => $otherUser->id,
            ]);

        $this->assertDatabaseHas('offers', [
            'user_id' => $this->user->id,
        ]);
    });

    test('another user cannot be selected as owner', function () {
        $otherUser = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
                'user_id' => $otherUser->id,
                'owner_id' => $otherUser->id,
            ]);

        $offer = Offer::where('name', 'Test Offer')->first();
        $this->assertNotNull($offer);
        $this->assertEquals($this->user->id, $offer->user_id);
    });
});

describe('POST /api/v1/offers — Name validation', function () {
    test('name is required', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('name must be a string', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 123,
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    });

    test('name cannot exceed 255', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => str_repeat('a', 256),
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('surrounding whitespace is trimmed from name', function () {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => '  Fitness Trial  ',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $this->assertDatabaseHas('offers', [
            'user_id' => $this->user->id,
            'name' => 'Fitness Trial',
        ]);
    });
});

describe('POST /api/v1/offers — URL validation', function () {
    test('destination_url is required', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_url']);
    });

    test('valid HTTP URL is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'http://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    });

    test('valid HTTPS URL is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    });

    test('FTP URL is rejected', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'ftp://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_url']);
    });

    test('javascript URL is rejected', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'javascript:alert(1)',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_url']);
    });

    test('malformed URL is rejected', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'not-a-url',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_url']);
    });

    test('value over 2048 is rejected', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com/'.str_repeat('a', 2049),
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_url']);
    });

    test('surrounding whitespace is trimmed from destination_url', function () {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => '  https://example.com  ',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $this->assertDatabaseHas('offers', [
            'user_id' => $this->user->id,
            'destination_url' => 'https://example.com',
        ]);
    });
});

describe('POST /api/v1/offers — Payout validation', function () {
    test('payout is required', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payout']);
    });

    test('zero is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '0',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    });

    test('positive integer is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    });

    test('two decimals are accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.99',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    });

    test('negative payout is rejected', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '-1',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payout']);
    });

    test('three decimal places are rejected', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.999',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payout']);
    });

    test('value above 9999999999.99 is rejected', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10000000000.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payout']);
    });

    test('exact maximum is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '9999999999.99',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    });

    test('payout does not suffer floating-point corruption', function () {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '0.10',
                'status' => 'draft',
            ]);

        $offer = Offer::where('user_id', $this->user->id)->first();
        $this->assertSame('0.10', $offer->payout);
    });

    test('resource serializes payout as two-decimal string', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '25.5',
                'status' => 'draft',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.offer.payout', '25.50');
    });
});

describe('POST /api/v1/offers — Status validation', function () {
    test('draft status is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    });

    test('active status is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'active',
            ]);

        $response->assertStatus(201);
    });

    test('suspended status is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'suspended',
            ]);

        $response->assertStatus(201);
    });

    test('archived status is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'archived',
            ]);

        $response->assertStatus(201);
    });

    test('invalid status returns 422', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });

    test('model returns OfferStatus enum instance', function () {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'active',
            ]);

        $offer = Offer::where('user_id', $this->user->id)->first();
        $this->assertInstanceOf(OfferStatus::class, $offer->status);
        $this->assertEquals(OfferStatus::Active, $offer->status);
    });
});

describe('POST /api/v1/offers — Description validation', function () {
    test('null description is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
                'description' => null,
            ]);

        $response->assertStatus(201);
    });

    test('string description is accepted', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
                'description' => 'A valid description',
            ]);

        $response->assertStatus(201);
    });

    test('description over 10000 is rejected', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offers', [
                'name' => 'Test Offer',
                'destination_url' => 'https://example.com',
                'payout' => '10.00',
                'status' => 'draft',
                'description' => str_repeat('a', 10001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    });
});

describe('GET /api/v1/offers — Listing', function () {
    test('response is paginated', function () {
        Offer::factory()->count(3)->forUser($this->user)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/offers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'destination_url', 'payout', 'status', 'description', 'created_at', 'updated_at'],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    test('default page contains at most 15 records', function () {
        Offer::factory()->count(20)->forUser($this->user)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/offers');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonCount(15, 'data');
    });

    test('only authenticated user offers appear', function () {
        Offer::factory()->count(3)->forUser($this->user)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/offers');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    });

    test('another user offers are excluded', function () {
        Offer::factory()->count(3)->forUser($this->user)->create();
        $otherUser = User::factory()->create();
        Offer::factory()->count(5)->forUser($otherUser)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/offers');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    });

    test('results are ordered by id descending', function () {
        Offer::factory()->forUser($this->user)->create(['name' => 'First']);
        Offer::factory()->forUser($this->user)->create(['name' => 'Second']);
        Offer::factory()->forUser($this->user)->create(['name' => 'Third']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/offers');

        $data = $response->json('data');
        $this->assertEquals('Third', $data[0]['name']);
        $this->assertEquals('Second', $data[1]['name']);
        $this->assertEquals('First', $data[2]['name']);
    });

    test('empty list has valid data links and meta', function () {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/offers');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1);
    });

    test('resource fields are correct', function () {
        Offer::factory()->forUser($this->user)->create([
            'name' => 'VPN Free Trial',
            'destination_url' => 'https://example.com/vpn',
            'payout' => '3.50',
            'status' => OfferStatus::Active,
            'description' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/offers');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'VPN Free Trial')
            ->assertJsonPath('data.0.destination_url', 'https://example.com/vpn')
            ->assertJsonPath('data.0.payout', '3.50')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.description', null);
    });

    test('user_id is absent from response', function () {
        Offer::factory()->forUser($this->user)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/offers');

        $response->assertStatus(200);
        foreach ($response->json('data') as $offer) {
            $this->assertArrayNotHasKey('user_id', $offer);
        }
    });
});

describe('Relationships and deletion', function () {
    test('User::offers returns owned offers', function () {
        Offer::factory()->count(3)->forUser($this->user)->create();

        $this->assertCount(3, $this->user->offers);
    });

    test('Offer::user returns owner', function () {
        $offer = Offer::factory()->forUser($this->user)->create();

        $this->assertEquals($this->user->id, $offer->user->id);
    });

    test('deleting a user cascades their offers', function () {
        Offer::factory()->count(3)->forUser($this->user)->create();
        $this->assertCount(3, $this->user->offers);

        $this->user->delete();

        $this->assertCount(0, Offer::where('user_id', $this->user->id)->get());
    });

    test('deleting one user does not delete another users offers', function () {
        $otherUser = User::factory()->create();
        Offer::factory()->count(2)->forUser($this->user)->create();
        Offer::factory()->count(3)->forUser($otherUser)->create();

        $this->user->delete();

        $this->assertCount(0, Offer::where('user_id', $this->user->id)->get());
        $this->assertCount(3, Offer::where('user_id', $otherUser->id)->get());
    });
});
