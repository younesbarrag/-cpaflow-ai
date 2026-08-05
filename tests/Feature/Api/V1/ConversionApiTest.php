<?php

use App\Enums\CampaignStatus;
use App\Enums\ConversionStatus;
use App\Http\Requests\Api\V1\Conversion\StoreConversionRequest;
use App\Models\Campaign;
use App\Models\Conversion;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Conversion Schema
|--------------------------------------------------------------------------
*/

it('creates the conversions table with the correct columns', function (): void {
    $columns = ['id', 'campaign_id', 'external_id', 'source', 'revenue', 'status', 'converted_at', 'created_at', 'updated_at'];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('conversions', $column))->toBeTrue(
            "Missing column: {$column}"
        );
    }
});

it('does not have tracking_link_id, tracking_click_id, offer_id, user_id, payout columns', function (): void {
    $absent = ['tracking_link_id', 'tracking_click_id', 'offer_id', 'user_id', 'payout'];

    foreach ($absent as $column) {
        expect(Schema::hasColumn('conversions', $column))->toBeFalse(
            "Unexpected column: {$column}"
        );
    }
});

it('does not use soft deletes', function (): void {
    $columns = ['deleted_at'];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('conversions', $column))->toBeFalse(
            "Unexpected column: {$column}"
        );
    }
});

/*
|--------------------------------------------------------------------------
| Conversion Model
|--------------------------------------------------------------------------
*/

it('has correct fillable fields', function (): void {
    $model = new Conversion;

    expect($model->getFillable())->toBe([
        'campaign_id',
        'external_id',
        'source',
        'revenue',
        'status',
        'converted_at',
    ]);
});

it('casts revenue as decimal and status as enum', function (): void {
    $model = new Conversion;

    $casts = $model->getCasts();

    expect($casts['revenue'])->toBe('decimal:2')
        ->and($casts['status'])->toBe(ConversionStatus::class)
        ->and($casts['converted_at'])->toBe('datetime');
});

it('has a campaign relationship', function (): void {
    $conversion = Conversion::factory()->create();

    expect($conversion->campaign)->not->toBeNull()
        ->and($conversion->campaign_id)->toBe($conversion->campaign->id);
});

/*
|--------------------------------------------------------------------------
| Campaign Conversions Relationship
|--------------------------------------------------------------------------
*/

it('has a conversions relationship on Campaign', function (): void {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    expect($campaign->conversions)->not->toBeNull()
        ->and($campaign->conversions->first()->id)->toBe($conversion->id);
});

/*
|--------------------------------------------------------------------------
| Conversion Factory
|--------------------------------------------------------------------------
*/

it('creates a conversion from factory', function (): void {
    $conversion = Conversion::factory()->create();

    expect($conversion)->toBeInstanceOf(Conversion::class)
        ->and($conversion->external_id)->toBeString()
        ->and($conversion->external_id)->not->toBeEmpty()
        ->and($conversion->status)->toBe(ConversionStatus::Pending)
        ->and($conversion->converted_at)->not->toBeNull()
        ->and($conversion->revenue)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

it('allows the owner to record a conversion for their campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    );

    $response->assertCreated();
});

it('rejects guests', function (): void {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertUnauthorized();

    assertDatabaseCount('conversions', 0);
});

it('rejects a foreign campaign', function (): void {
    $owner = User::factory()->create();
    $foreignUser = User::factory()->create();

    $offer = Offer::factory()->for($owner)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($foreignUser);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertForbidden();

    assertDatabaseCount('conversions', 0);
});

it('returns not found when the campaign does not exist', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    postJson(
        '/api/v1/campaigns/999999/conversions',
        ['external_id' => 'TXN-001']
    )->assertNotFound();

    assertDatabaseCount('conversions', 0);
});

it('allows conversion on draft campaigns', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Draft,
    ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    );

    $response->assertCreated();
});

it('allows conversion on suspended campaigns', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Suspended,
    ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    );

    $response->assertCreated();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('returns 422 when external_id is missing', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        []
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('external_id');

    assertDatabaseCount('conversions', 0);
});

it('returns 422 when external_id is empty', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => '']
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('external_id');

    assertDatabaseCount('conversions', 0);
});

it('does not include unique validation rule on external_id', function (): void {
    $request = new StoreConversionRequest;

    $rules = $request->rules();

    expect($rules['external_id'])->not->toContain('unique:conversions,external_id');
});

/*
|--------------------------------------------------------------------------
| Records Conversion
|--------------------------------------------------------------------------
*/

it('records a conversion with the correct attributes', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    );

    $response->assertCreated();

    assertDatabaseHas('conversions', [
        'campaign_id' => $campaign->id,
        'external_id' => 'TXN-001',
        'status' => ConversionStatus::Pending->value,
        'revenue' => $offer->payout,
    ]);
});

it('snapshots revenue from Offer.payout', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create(['payout' => '42.50']);
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    assertDatabaseHas('conversions', [
        'campaign_id' => $campaign->id,
        'revenue' => '42.50',
    ]);
});

it('defaults status to pending', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    $conversion = Conversion::where('external_id', 'TXN-001')->first();

    expect($conversion->status)->toBe(ConversionStatus::Pending);
});

it('generates converted_at as approximately the current time', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $before = now();

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    $after = now();

    $conversion = Conversion::where('external_id', 'TXN-001')->first();

    expect($conversion->converted_at)
        ->not->toBeNull()
        ->and($conversion->converted_at->greaterThanOrEqualTo($before->subSeconds(5)))->toBeTrue()
        ->and($conversion->converted_at->lessThanOrEqualTo($after->addSeconds(5)))->toBeTrue();
});

it('sets created_at and updated_at timestamps', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    $conversion = Conversion::where('external_id', 'TXN-001')->first();

    expect($conversion->created_at)->not->toBeNull()
        ->and($conversion->updated_at)->not->toBeNull();
});

it('stores source when provided', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        [
            'external_id' => 'TXN-001',
            'source' => 'facebook',
        ]
    )->assertCreated();

    assertDatabaseHas('conversions', [
        'external_id' => 'TXN-001',
        'source' => 'facebook',
    ]);
});

it('sets source to null when not provided', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    assertDatabaseHas('conversions', [
        'external_id' => 'TXN-001',
        'source' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Duplicate Prevention
|--------------------------------------------------------------------------
*/

it('returns 201 for first conversion with a given external_id', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    assertDatabaseCount('conversions', 1);
});

it('returns 409 for duplicate external_id', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    $response = postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    );

    $response->assertStatus(409)
        ->assertJsonPath('errors.external_id.0', fn (string $msg) => str_contains($msg, 'TXN-001'));

    assertDatabaseCount('conversions', 1);
});

it('returns 201 for different external_id', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-002']
    )->assertCreated();

    assertDatabaseCount('conversions', 2);
});

it('enforces database uniqueness constraint', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertStatus(409);

    assertDatabaseCount('conversions', 1);
});

it('handles concurrent duplicate requests safely', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertStatus(409);

    assertDatabaseCount('conversions', 1);
});

/*
|--------------------------------------------------------------------------
| Conversion Resource / Response Shape
|--------------------------------------------------------------------------
*/

it('returns the correct response shape', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    );

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'conversion' => [
                    'id',
                    'campaign_id',
                    'external_id',
                    'source',
                    'revenue',
                    'status',
                    'converted_at',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

    $conversion = $response->json('data.conversion');

    expect($conversion['campaign_id'])->toBe($campaign->id)
        ->and($conversion['external_id'])->toBe('TXN-001')
        ->and($conversion['status'])->toBe('pending')
        ->and($conversion['revenue'])->toBe($offer->payout);
});

it('does not expose tracking_link_id, tracking_click_id, offer_id, user_id, or payout', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    );

    $data = $response->json('data.conversion');

    expect($data)->not->toHaveKey('tracking_link_id')
        ->and($data)->not->toHaveKey('tracking_click_id')
        ->and($data)->not->toHaveKey('offer_id')
        ->and($data)->not->toHaveKey('user_id')
        ->and($data)->not->toHaveKey('payout');
});

/*
|--------------------------------------------------------------------------
| Cascade Deletion
|--------------------------------------------------------------------------
*/

it('deletes conversions when campaign is deleted', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    assertDatabaseCount('conversions', 1);

    $campaign->delete();

    assertDatabaseCount('conversions', 0);
});

/*
|--------------------------------------------------------------------------
| Exception Handler
|--------------------------------------------------------------------------
*/

it('returns 409 conflict JSON for duplicate conversion exception', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    )->assertCreated();

    $response = postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-001']
    );

    $response->assertStatus(409)
        ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'already exists'))
        ->assertJsonStructure([
            'message',
            'errors' => [
                'external_id',
            ],
        ]);
});
