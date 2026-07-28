<?php

use App\Enums\CampaignStatus;
use App\Enums\OfferStatus;
use App\Models\Campaign;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows an authenticated owner to create a draft campaign', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
        'status' => OfferStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $offer->id,
            'name' => '  Facebook Morocco Campaign  ',
            'traffic_source' => '  Facebook Ads  ',
            'budget' => '1500.00',
        ],
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.campaign.name',
            'Facebook Morocco Campaign',
        )
        ->assertJsonPath(
            'data.campaign.traffic_source',
            'Facebook Ads',
        )
        ->assertJsonPath(
            'data.campaign.budget',
            '1500.00',
        )
        ->assertJsonPath(
            'data.campaign.status',
            CampaignStatus::Draft->value,
        )
        ->assertJsonPath(
            'data.campaign.offer.id',
            $offer->id,
        );

    $this->assertDatabaseHas('campaigns', [
        'offer_id' => $offer->id,
        'name' => 'Facebook Morocco Campaign',
        'traffic_source' => 'Facebook Ads',
        'budget' => '1500.00',
        'status' => CampaignStatus::Draft->value,
    ]);
});

it('does not allow a guest to create a campaign', function () {
    $offer = Offer::factory()->create([
        'status' => OfferStatus::Active,
    ]);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $offer->id,
            'name' => 'Guest Campaign',
            'traffic_source' => 'Google Ads',
            'budget' => '500.00',
        ],
    );

    $response->assertUnauthorized();

    $this->assertDatabaseCount('campaigns', 0);
});

it('returns 404 when the selected offer does not exist', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => 999999,
            'name' => 'Missing Offer Campaign',
            'traffic_source' => 'Newsletter',
            'budget' => '100.00',
        ],
    );

    $response->assertNotFound();

    $this->assertDatabaseCount('campaigns', 0);
});

it('does not allow creating a campaign for another users offer', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $foreignOffer = Offer::factory()->create([
        'user_id' => $owner->id,
        'status' => OfferStatus::Active,
    ]);

    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $foreignOffer->id,
            'name' => 'Foreign Offer Campaign',
            'traffic_source' => 'TikTok',
            'budget' => '750.00',
        ],
    );

    $response->assertForbidden();

    $this->assertDatabaseCount('campaigns', 0);
});

it('does not allow creating a campaign for an archived owned offer', function () {
    $user = User::factory()->create();

    $archivedOffer = Offer::factory()->create([
        'user_id' => $user->id,
        'status' => OfferStatus::Archived,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $archivedOffer->id,
            'name' => 'Archived Offer Campaign',
            'traffic_source' => 'Instagram',
            'budget' => '300.00',
        ],
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['offer_id']);

    $this->assertDatabaseCount('campaigns', 0);
});

it('rejects a client supplied campaign status during creation', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
        'status' => OfferStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $offer->id,
            'name' => 'Direct Active Campaign',
            'traffic_source' => 'Google Ads',
            'budget' => '900.00',
            'status' => CampaignStatus::Active->value,
        ],
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->assertDatabaseCount('campaigns', 0);
});
it('rejects an empty campaign name after normalization', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
        'status' => OfferStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $offer->id,
            'name' => '   ',
            'traffic_source' => 'Facebook Ads',
            'budget' => '500.00',
        ],
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseCount('campaigns', 0);
});

it('rejects an empty traffic source after normalization', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
        'status' => OfferStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $offer->id,
            'name' => 'Facebook Morocco Campaign',
            'traffic_source' => '   ',
            'budget' => '500.00',
        ],
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['traffic_source']);

    $this->assertDatabaseCount('campaigns', 0);
});

it('rejects a negative campaign budget', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
        'status' => OfferStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $offer->id,
            'name' => 'Negative Budget Campaign',
            'traffic_source' => 'Google Ads',
            'budget' => '-1.00',
        ],
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['budget']);

    $this->assertDatabaseCount('campaigns', 0);
});

it('rejects a campaign budget with excessive decimal precision', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
        'status' => OfferStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.store'),
        [
            'offer_id' => $offer->id,
            'name' => 'Invalid Precision Campaign',
            'traffic_source' => 'TikTok',
            'budget' => '500.999',
        ],
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['budget']);

    $this->assertDatabaseCount('campaigns', 0);
});
it('lists only campaigns belonging to the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownedOffer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $foreignOffer = Offer::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $olderCampaign = Campaign::factory()->create([
        'offer_id' => $ownedOffer->id,
        'name' => 'Older Owned Campaign',
    ]);

    $newerCampaign = Campaign::factory()->create([
        'offer_id' => $ownedOffer->id,
        'name' => 'Newer Owned Campaign',
    ]);

    Campaign::factory()->create([
        'offer_id' => $foreignOffer->id,
        'name' => 'Foreign Campaign',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.campaigns.index'),
    );

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newerCampaign->id)
        ->assertJsonPath('data.1.id', $olderCampaign->id)
        ->assertJsonPath('data.0.offer.id', $ownedOffer->id)
        ->assertJsonMissing([
            'name' => 'Foreign Campaign',
        ]);
});

it('paginates authenticated users campaigns by fifteen', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    Campaign::factory()
        ->count(18)
        ->create([
            'offer_id' => $offer->id,
        ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.campaigns.index'),
    );

    $response
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 18)
        ->assertJsonPath('meta.last_page', 2);
});

it('does not allow a guest to list campaigns', function () {
    Campaign::factory()->create();

    $response = $this->getJson(
        route('api.v1.campaigns.index'),
    );

    $response->assertUnauthorized();
});

it('allows the owner to view a campaign', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $offer->id,
        'name' => 'Owned Campaign',
        'traffic_source' => 'Newsletter',
        'budget' => '750.00',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.campaigns.show', $campaign),
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.campaign.id', $campaign->id)
        ->assertJsonPath('data.campaign.name', 'Owned Campaign')
        ->assertJsonPath(
            'data.campaign.traffic_source',
            'Newsletter',
        )
        ->assertJsonPath('data.campaign.budget', '750.00')
        ->assertJsonPath('data.campaign.offer.id', $offer->id)
        ->assertJsonPath('data.campaign.offer.name', $offer->name);
});

it('returns 403 when viewing another users campaign', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $owner->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $offer->id,
    ]);

    Sanctum::actingAs($otherUser);

    $response = $this->getJson(
        route('api.v1.campaigns.show', $campaign),
    );

    $response->assertForbidden();
});

it('returns 404 when viewing a missing campaign', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson(
        route('api.v1.campaigns.show', 999999),
    );

    $response->assertNotFound();
});
it('allows the owner to partially update a campaign', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $offer->id,
        'name' => 'Original Campaign',
        'traffic_source' => 'Facebook Ads',
        'budget' => '500.00',
        'status' => CampaignStatus::Draft,
    ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson(
        route('api.v1.campaigns.update', $campaign),
        [
            'name' => '  Updated Campaign  ',
            'budget' => '750.50',
        ],
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.campaign.name',
            'Updated Campaign',
        )
        ->assertJsonPath(
            'data.campaign.traffic_source',
            'Facebook Ads',
        )
        ->assertJsonPath(
            'data.campaign.budget',
            '750.50',
        )
        ->assertJsonPath(
            'data.campaign.status',
            CampaignStatus::Draft->value,
        );

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'offer_id' => $offer->id,
        'name' => 'Updated Campaign',
        'traffic_source' => 'Facebook Ads',
        'budget' => '750.50',
        'status' => CampaignStatus::Draft->value,
    ]);
});

it('rejects an empty campaign update request', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $offer->id,
        'name' => 'Unchanged Campaign',
    ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson(
        route('api.v1.campaigns.update', $campaign),
        [],
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['campaign']);

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'name' => 'Unchanged Campaign',
    ]);
});

it('rejects protected campaign fields even alongside valid fields', function () {
    $user = User::factory()->create();

    $originalOffer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $otherOffer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $originalOffer->id,
        'name' => 'Protected Campaign',
        'status' => CampaignStatus::Draft,
    ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson(
        route('api.v1.campaigns.update', $campaign),
        [
            'name' => 'Attempted Update',
            'offer_id' => $otherOffer->id,
            'status' => CampaignStatus::Active->value,
            'user_id' => $user->id,
        ],
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'offer_id',
            'status',
            'user_id',
        ]);

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'offer_id' => $originalOffer->id,
        'name' => 'Protected Campaign',
        'status' => CampaignStatus::Draft->value,
    ]);
});

it('returns 403 before validation when updating another users campaign', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $owner->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $offer->id,
        'name' => 'Foreign Campaign',
    ]);

    Sanctum::actingAs($otherUser);

    $response = $this->patchJson(
        route('api.v1.campaigns.update', $campaign),
        [
            'budget' => '-100.999',
            'status' => CampaignStatus::Active->value,
        ],
    );

    $response->assertForbidden();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'name' => 'Foreign Campaign',
    ]);
});

it('does not allow a guest to update a campaign', function () {
    $campaign = Campaign::factory()->create([
        'name' => 'Guest Protected Campaign',
    ]);

    $response = $this->patchJson(
        route('api.v1.campaigns.update', $campaign),
        [
            'name' => 'Unauthorized Update',
        ],
    );

    $response->assertUnauthorized();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'name' => 'Guest Protected Campaign',
    ]);
});

it('returns 404 when updating a missing campaign', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->patchJson(
        route('api.v1.campaigns.update', 999999),
        [
            'name' => 'Missing Campaign',
        ],
    );

    $response->assertNotFound();
});
it('allows the owner to activate a draft campaign', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $offer->id,
        'status' => CampaignStatus::Draft,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.activate', $campaign),
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.campaign.status',
            CampaignStatus::Active->value,
        );

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Active->value,
    ]);
});

it('allows the owner to suspend an active campaign', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->active()->create([
        'offer_id' => $offer->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.suspend', $campaign),
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.campaign.status',
            CampaignStatus::Suspended->value,
        );

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Suspended->value,
    ]);
});

it('allows the owner to reactivate a suspended campaign', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->suspended()->create([
        'offer_id' => $offer->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.activate', $campaign),
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.campaign.status',
            CampaignStatus::Active->value,
        );

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Active->value,
    ]);
});

it('rejects suspending a draft campaign without writing to the database', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $offer->id,
        'status' => CampaignStatus::Draft,
    ]);

    $originalUpdatedAt = $campaign->getRawOriginal('updated_at');

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.suspend', $campaign),
    );

    $response
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'The campaign transition is not allowed.',
        )
        ->assertJsonValidationErrors(['status']);

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Draft);
    expect($campaign->getRawOriginal('updated_at'))
        ->toBe($originalUpdatedAt);
});

it('rejects activating an already active campaign without writing to the database', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->active()->create([
        'offer_id' => $offer->id,
    ]);

    $originalUpdatedAt = $campaign->getRawOriginal('updated_at');

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.activate', $campaign),
    );

    $response
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'The campaign transition is not allowed.',
        )
        ->assertJsonValidationErrors(['status']);

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Active);
    expect($campaign->getRawOriginal('updated_at'))
        ->toBe($originalUpdatedAt);
});

it('rejects suspending an already suspended campaign without writing to the database', function () {
    $user = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $user->id,
    ]);

    $campaign = Campaign::factory()->suspended()->create([
        'offer_id' => $offer->id,
    ]);

    $originalUpdatedAt = $campaign->getRawOriginal('updated_at');

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.suspend', $campaign),
    );

    $response
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'The campaign transition is not allowed.',
        )
        ->assertJsonValidationErrors(['status']);

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Suspended);
    expect($campaign->getRawOriginal('updated_at'))
        ->toBe($originalUpdatedAt);
});
it('does not allow a guest to activate a campaign', function () {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Draft,
    ]);

    $response = $this->postJson(
        route('api.v1.campaigns.activate', $campaign),
    );

    $response->assertUnauthorized();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Draft->value,
    ]);
});

it('does not allow a guest to suspend a campaign', function () {
    $campaign = Campaign::factory()->active()->create();

    $response = $this->postJson(
        route('api.v1.campaigns.suspend', $campaign),
    );

    $response->assertUnauthorized();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Active->value,
    ]);
});

it('returns 403 when another user tries to activate a campaign', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $owner->id,
    ]);

    $campaign = Campaign::factory()->create([
        'offer_id' => $offer->id,
        'status' => CampaignStatus::Draft,
    ]);

    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        route('api.v1.campaigns.activate', $campaign),
    );

    $response->assertForbidden();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Draft->value,
    ]);
});

it('returns 403 when another user tries to suspend a campaign', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $offer = Offer::factory()->create([
        'user_id' => $owner->id,
    ]);

    $campaign = Campaign::factory()->active()->create([
        'offer_id' => $offer->id,
    ]);

    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        route('api.v1.campaigns.suspend', $campaign),
    );

    $response->assertForbidden();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Active->value,
    ]);
});

it('returns 404 when activating a missing campaign', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.activate', 999999),
    );

    $response->assertNotFound();
});

it('returns 404 when suspending a missing campaign', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson(
        route('api.v1.campaigns.suspend', 999999),
    );

    $response->assertNotFound();
});
