<?php

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('allows the owner to update an offer', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create([
            'name' => 'Original Offer',
        ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'name' => 'Updated Offer',
        ],
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.offer.id', $offer->id)
        ->assertJsonPath('data.offer.name', 'Updated Offer');

    expect($offer->fresh()->name)->toBe('Updated Offer');
});

it('returns forbidden when another user tries to update an offer', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()
        ->for($owner)
        ->create([
            'name' => 'Protected Offer',
        ]);

    Sanctum::actingAs($otherUser);

    $response = $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'name' => 'Unauthorized Update',
        ],
    );

    $response->assertForbidden();

    expect($offer->fresh()->name)->toBe('Protected Offer');
});

it('returns forbidden before validation for a foreign offer', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()
        ->for($owner)
        ->create();

    Sanctum::actingAs($otherUser);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'payout' => 'invalid-value',
        ],
    )->assertForbidden();
});

it('requires authentication to update an offer', function (): void {
    $offer = Offer::factory()->create();

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'name' => 'Updated Offer',
        ],
    )->assertUnauthorized();
});

it('allows the owner to archive an offer', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->active()
        ->create();

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.offers.archive', $offer),
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.offer.id', $offer->id)
        ->assertJsonPath(
            'data.offer.status',
            OfferStatus::Archived->value,
        );

    expect($offer->fresh()->status)
        ->toBe(OfferStatus::Archived);
});

it('returns forbidden when another user tries to archive an offer', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()
        ->for($owner)
        ->active()
        ->create();

    Sanctum::actingAs($otherUser);

    $this->postJson(
        route('api.v1.offers.archive', $offer),
    )->assertForbidden();

    expect($offer->fresh()->status)
        ->toBe(OfferStatus::Active);
});

it('requires authentication to archive an offer', function (): void {
    $offer = Offer::factory()
        ->active()
        ->create();

    $this->postJson(
        route('api.v1.offers.archive', $offer),
    )->assertUnauthorized();
});
it('updates only the submitted offer fields', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create([
            'name' => 'Original Name',
            'destination_url' => 'https://example.com/original',
            'payout' => '10.00',
            'status' => OfferStatus::Draft,
            'description' => 'Original description',
        ]);

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'payout' => '42.75',
        ],
    )->assertOk();

    $offer->refresh();

    expect($offer->name)->toBe('Original Name')
        ->and($offer->destination_url)
        ->toBe('https://example.com/original')
        ->and($offer->payout)->toBe('42.75')
        ->and($offer->status)->toBe(OfferStatus::Draft)
        ->and($offer->description)->toBe('Original description');
});

it('rejects an empty update request', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [],
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer');
});

it('rejects a request containing only ownership fields', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create();

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'user_id' => $otherUser->id,
            'owner_id' => $otherUser->id,
            'affiliate_id' => $otherUser->id,
        ],
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer');

    expect($offer->fresh()->user_id)->toBe($user->id);
});

it('does not allow ownership transfer during a valid update', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()
        ->for($owner)
        ->create([
            'name' => 'Original Offer',
        ]);

    Sanctum::actingAs($owner);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'name' => 'Updated Offer',
            'user_id' => $otherUser->id,
            'owner_id' => $otherUser->id,
            'affiliate_id' => $otherUser->id,
        ],
    )
        ->assertOk()
        ->assertJsonPath('data.offer.name', 'Updated Offer');

    $offer->refresh();

    expect($offer->name)->toBe('Updated Offer')
        ->and($offer->user_id)->toBe($owner->id);
});

it('rejects a destination URL using an unsupported protocol', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'destination_url' => 'ftp://example.com/offer',
        ],
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors('destination_url');
});

it('rejects payout with excessive decimal precision', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'payout' => '25.555',
        ],
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors('payout');
});

it('rejects a negative payout', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'payout' => '-1.00',
        ],
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors('payout');
});

it('normalizes submitted text fields', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->patchJson(
        route('api.v1.offers.update', $offer),
        [
            'name' => '  Updated Offer  ',
            'destination_url' => '  https://example.com/updated  ',
            'description' => '  Updated description  ',
        ],
    )->assertOk();

    $offer->refresh();

    expect($offer->name)->toBe('Updated Offer')
        ->and($offer->destination_url)
        ->toBe('https://example.com/updated')
        ->and($offer->description)
        ->toBe('Updated description');
});
it('filters the authenticated user offers by status', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $activeOffer = Offer::factory()
        ->for($user)
        ->active()
        ->create();

    $draftOffer = Offer::factory()
        ->for($user)
        ->draft()
        ->create();

    $foreignActiveOffer = Offer::factory()
        ->for($otherUser)
        ->active()
        ->create();

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.offers.index', [
            'status' => OfferStatus::Active->value,
        ]),
    );

    $response->assertOk();

    $offerIds = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($offerIds)
        ->toContain($activeOffer->id)
        ->not->toContain($draftOffer->id)
        ->not->toContain($foreignActiveOffer->id);
});

it('rejects an invalid offer status filter', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson(
        route('api.v1.offers.index', [
            'status' => 'invalid-status',
        ]),
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('searches offers by name', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $matchingOffer = Offer::factory()
        ->for($user)
        ->create([
            'name' => 'Fitness Trial Offer',
        ]);

    $nonMatchingOffer = Offer::factory()
        ->for($user)
        ->create([
            'name' => 'Travel Discount',
        ]);

    $foreignMatchingOffer = Offer::factory()
        ->for($otherUser)
        ->create([
            'name' => 'Fitness Foreign Offer',
        ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.offers.index', [
            'search' => 'fitness',
        ]),
    );

    $response->assertOk();

    $offerIds = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($offerIds)
        ->toContain($matchingOffer->id)
        ->not->toContain($nonMatchingOffer->id)
        ->not->toContain($foreignMatchingOffer->id);
});

it('normalizes whitespace in the search query', function (): void {
    $user = User::factory()->create();

    $matchingOffer = Offer::factory()
        ->for($user)
        ->create([
            'name' => 'Fitness Trial Offer',
        ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.offers.index', [
            'search' => '   Fitness    Trial   ',
        ]),
    );

    $response->assertOk();

    $offerIds = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($offerIds)->toContain($matchingOffer->id);
});

it('combines status filtering and name search', function (): void {
    $user = User::factory()->create();

    $matchingOffer = Offer::factory()
        ->for($user)
        ->active()
        ->create([
            'name' => 'Fitness Subscription',
        ]);

    $wrongStatusOffer = Offer::factory()
        ->for($user)
        ->draft()
        ->create([
            'name' => 'Fitness Draft Offer',
        ]);

    $wrongNameOffer = Offer::factory()
        ->for($user)
        ->active()
        ->create([
            'name' => 'Travel Subscription',
        ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.offers.index', [
            'status' => OfferStatus::Active->value,
            'search' => 'fitness',
        ]),
    );

    $response->assertOk();

    $offerIds = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($offerIds)
        ->toBe([$matchingOffer->id])
        ->not->toContain($wrongStatusOffer->id)
        ->not->toContain($wrongNameOffer->id);
});

it('preserves pagination metadata when filters are applied', function (): void {
    $user = User::factory()->create();

    Offer::factory()
        ->count(18)
        ->for($user)
        ->active()
        ->create([
            'name' => 'Fitness Offer',
        ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.offers.index', [
            'status' => OfferStatus::Active->value,
            'search' => 'fitness',
        ]),
    );

    $response
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 18)
        ->assertJsonPath('meta.last_page', 2);
});

it('archives an already archived offer idempotently', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create([
            'status' => OfferStatus::Archived,
        ]);

    Sanctum::actingAs($user);

    $originalUpdatedAt = $offer->updated_at->copy();

    $response = $this->postJson(
        route('api.v1.offers.archive', $offer),
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.offer.status',
            OfferStatus::Archived->value,
        );

    $offer->refresh();

    expect($offer->status)->toBe(OfferStatus::Archived)
        ->and($offer->updated_at->equalTo($originalUpdatedAt))
        ->toBeTrue();
});
