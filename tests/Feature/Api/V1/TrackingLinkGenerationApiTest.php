<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Offer;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\TrackingLink\TrackingCodeGenerator;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

it('allows the owner to generate a tracking link for an active campaign', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Active,
        ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    );

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'tracking_link' => [
                    'id',
                    'campaign_id',
                    'code',
                    'url',
                    'created_at',
                    'updated_at',
                ],
            ],
        ])
        ->assertJsonPath(
            'data.tracking_link.campaign_id',
            $campaign->id
        );

    $trackingLink = $response->json('data.tracking_link');

    expect($trackingLink['code'])
        ->toBeString()
        ->toMatch('/^[A-Za-z0-9]{32}$/');

    expect($trackingLink['url'])
        ->toBe(url('/t/'.$trackingLink['code']));

    assertDatabaseHas('tracking_links', [
        'id' => $trackingLink['id'],
        'campaign_id' => $campaign->id,
        'code' => $trackingLink['code'],
    ]);
});

it('rejects guests', function (): void {
    $campaign = Campaign::factory()
        ->create([
            'status' => CampaignStatus::Active,
        ]);

    postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )->assertUnauthorized();

    assertDatabaseCount('tracking_links', 0);
});

it('rejects a foreign campaign', function (): void {
    $owner = User::factory()->create();
    $foreignUser = User::factory()->create();

    $offer = Offer::factory()
        ->for($owner)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Active,
        ]);

    Sanctum::actingAs($foreignUser);

    postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )->assertForbidden();

    assertDatabaseCount('tracking_links', 0);
});

it('authorizes ownership before validating campaign status', function (): void {
    $owner = User::factory()->create();
    $foreignUser = User::factory()->create();

    $offer = Offer::factory()
        ->for($owner)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Suspended,
        ]);

    Sanctum::actingAs($foreignUser);

    postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )->assertForbidden();

    assertDatabaseCount('tracking_links', 0);
});

it('returns not found when the campaign does not exist', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    postJson(
        '/api/v1/campaigns/999999/tracking-links'
    )->assertNotFound();

    assertDatabaseCount('tracking_links', 0);
});

it('rejects a draft campaign', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Draft,
        ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    assertDatabaseCount('tracking_links', 0);
});

it('rejects a suspended campaign', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Suspended,
        ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    assertDatabaseCount('tracking_links', 0);
});

it('creates independent tracking links on repeated generation', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Active,
        ]);

    Sanctum::actingAs($user);

    $firstResponse = postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )->assertCreated();

    $secondResponse = postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )->assertCreated();

    $firstCode = $firstResponse->json('data.tracking_link.code');
    $secondCode = $secondResponse->json('data.tracking_link.code');

    expect($firstCode)
        ->not->toBe($secondCode);

    assertDatabaseCount('tracking_links', 2);

    assertDatabaseHas('tracking_links', [
        'campaign_id' => $campaign->id,
        'code' => $firstCode,
    ]);

    assertDatabaseHas('tracking_links', [
        'campaign_id' => $campaign->id,
        'code' => $secondCode,
    ]);
});

it('returns only the approved tracking link fields', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Active,
        ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )->assertCreated();

    $trackingLink = $response->json('data.tracking_link');

    expect(array_keys($trackingLink))->toBe([
        'id',
        'campaign_id',
        'code',
        'url',
        'created_at',
        'updated_at',
    ]);
});

it('returns a generic server error when generation attempts are exhausted', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Active,
        ]);

    Sanctum::actingAs($user);

    $duplicateCode = str_repeat('X', 32);

    TrackingLink::factory()
        ->for($campaign)
        ->create(['code' => $duplicateCode]);

    app()->instance(
        TrackingCodeGenerator::class,
        new class($duplicateCode) extends TrackingCodeGenerator
        {
            public function __construct(
                private string $code,
            ) {}

            public function generate(): string
            {
                return $this->code;
            }
        }
    );

    postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )
        ->assertStatus(500)
        ->assertExactJson([
            'message' => 'The tracking link could not be generated.',
        ]);

    assertDatabaseCount('tracking_links', 1);
});

it('deletes tracking links when the campaign is deleted', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create();

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Active,
        ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )->assertCreated();

    assertDatabaseCount('tracking_links', 1);

    $campaign->delete();

    assertDatabaseCount('tracking_links', 0);
});

it('exposes no ownership or campaign details in the response', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()
        ->for($user)
        ->create([
            'destination_url' => 'https://example.com/offer',
        ]);

    $campaign = Campaign::factory()
        ->for($offer)
        ->create([
            'status' => CampaignStatus::Active,
            'name' => 'Test Campaign',
            'budget' => '1500.00',
            'traffic_source' => 'Facebook',
        ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.tracking-links.store', $campaign)
    )->assertCreated();

    $trackingLink = $response->json('data.tracking_link');

    expect($trackingLink)->not->toHaveKey('user_id');
    expect($trackingLink)->not->toHaveKey('offer_id');
    expect($trackingLink)->not->toHaveKey('destination_url');
    expect($trackingLink)->not->toHaveKey('is_active');
    expect($trackingLink)->not->toHaveKey('campaign');
    expect($trackingLink)->not->toHaveKey('offer');
    expect($trackingLink)->not->toHaveKey('name');
    expect($trackingLink)->not->toHaveKey('budget');
    expect($trackingLink)->not->toHaveKey('traffic_source');
});
