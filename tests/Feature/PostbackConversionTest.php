<?php

use App\Enums\CampaignStatus;
use App\Enums\ConversionStatus;
use App\Models\Campaign;
use App\Models\Conversion;
use App\Models\Offer;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\PostbackSigner;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function postbackUrl(TrackingLink $link, array $params = []): string
{
    $signer = app(PostbackSigner::class);
    $token = $signer->tokenFor($link->code);

    $defaults = [
        'external_id' => 'TXN-001',
        'token' => $token,
    ];

    return route('postback.conversion', $link->code).'?'.http_build_query(array_merge($defaults, $params));
}

/*
|--------------------------------------------------------------------------
| Happy Path
|--------------------------------------------------------------------------
*/

it('creates a conversion with valid code, token, and external_id', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create(['payout' => '25.00']);
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = getJson(postbackUrl($trackingLink, [
        'external_id' => 'NET-TXN-001',
    ]));

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'duplicate' => false,
        ]);

    assertDatabaseHas('conversions', [
        'campaign_id' => $campaign->id,
        'external_id' => 'NET-TXN-001',
        'status' => ConversionStatus::Pending->value,
        'revenue' => '25.00',
    ]);
});

it('conversion belongs to the campaign identified through the tracking link', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    getJson(postbackUrl($trackingLink, ['external_id' => 'TXN-OWNED']))->assertOk();

    $conversion = Conversion::where('external_id', 'TXN-OWNED')->first();
    expect($conversion->campaign_id)->toBe($campaign->id);
});

it('sets status to Pending', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    getJson(postbackUrl($trackingLink))->assertOk();

    $conversion = Conversion::where('external_id', 'TXN-001')->first();
    expect($conversion->status)->toBe(ConversionStatus::Pending);
});

it('snapshots revenue from Offer payout', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create(['payout' => '42.50']);
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    getJson(postbackUrl($trackingLink))->assertOk();

    assertDatabaseHas('conversions', [
        'external_id' => 'TXN-001',
        'revenue' => '42.50',
    ]);
});

it('populates converted_at', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $before = now();
    getJson(postbackUrl($trackingLink))->assertOk();
    $after = now();

    $conversion = Conversion::where('external_id', 'TXN-001')->first();
    expect($conversion->converted_at)
        ->not->toBeNull()
        ->and($conversion->converted_at->greaterThanOrEqualTo($before->subSeconds(5)))->toBeTrue()
        ->and($conversion->converted_at->lessThanOrEqualTo($after->addSeconds(5)))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Source
|--------------------------------------------------------------------------
*/

it('persists source when supplied', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    getJson(postbackUrl($trackingLink, [
        'external_id' => 'TXN-001',
        'source' => 'facebook',
    ]))->assertOk();

    assertDatabaseHas('conversions', [
        'external_id' => 'TXN-001',
        'source' => 'facebook',
    ]);
});

it('sets source to null when not supplied', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    getJson(postbackUrl($trackingLink))->assertOk();

    assertDatabaseHas('conversions', [
        'external_id' => 'TXN-001',
        'source' => null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('returns 422 when external_id is missing', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $signer = app(PostbackSigner::class);
    $token = $signer->tokenFor($trackingLink->code);

    $response = getJson(route('postback.conversion', $trackingLink->code).'?token='.$token);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('external_id');

    assertDatabaseCount('conversions', 0);
});

it('returns 422 when token is missing', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = getJson(route('postback.conversion', $trackingLink->code).'?external_id=TXN-001');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('token');

    assertDatabaseCount('conversions', 0);
});

it('returns JSON 422 even without Accept application/json header', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = $this->getJson(route('postback.conversion', $trackingLink->code).'?external_id=TXN-001');

    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json');
});

it('rejects prohibited revenue field', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $signer = app(PostbackSigner::class);
    $token = $signer->tokenFor($trackingLink->code);

    $response = getJson(route('postback.conversion', $trackingLink->code).'?external_id=TXN-001&token='.$token.'&revenue=999.99');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('revenue');
});

it('rejects prohibited status field', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $signer = app(PostbackSigner::class);
    $token = $signer->tokenFor($trackingLink->code);

    $response = getJson(route('postback.conversion', $trackingLink->code).'?external_id=TXN-001&token='.$token.'&status=approved');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('rejects prohibited campaign_id field', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $signer = app(PostbackSigner::class);
    $token = $signer->tokenFor($trackingLink->code);

    $response = getJson(route('postback.conversion', $trackingLink->code).'?external_id=TXN-001&token='.$token.'&campaign_id=999');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('campaign_id');
});

/*
|--------------------------------------------------------------------------
| Security: Token
|--------------------------------------------------------------------------
*/

it('returns 403 for invalid token', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = getJson(route('postback.conversion', $trackingLink->code).'?external_id=TXN-001&token=invalid-token-here');

    $response->assertForbidden();

    assertDatabaseCount('conversions', 0);
});

it('returns 422 for empty token', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = getJson(route('postback.conversion', $trackingLink->code).'?external_id=TXN-001&token=');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('token');

    assertDatabaseCount('conversions', 0);
});

/*
|--------------------------------------------------------------------------
| Security: Tracking Code
|--------------------------------------------------------------------------
*/

it('returns 404 for unknown tracking code', function (): void {
    $response = getJson('/postback/nonexistent123?external_id=TXN-001&token=fake');

    $response->assertNotFound();

    assertDatabaseCount('conversions', 0);
});

/*
|--------------------------------------------------------------------------
| Duplicate / Retry Idempotency
|--------------------------------------------------------------------------
*/

it('returns 200 with duplicate false for first conversion', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = getJson(postbackUrl($trackingLink, ['external_id' => 'TXN-DUP-001']));

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'duplicate' => false,
        ]);

    assertDatabaseCount('conversions', 1);
});

it('returns 200 with duplicate true for retry with same external_id', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    getJson(postbackUrl($trackingLink, ['external_id' => 'TXN-DUP-001']))->assertOk();

    $response = getJson(postbackUrl($trackingLink, ['external_id' => 'TXN-DUP-001']));

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'duplicate' => true,
        ]);

    assertDatabaseCount('conversions', 1);
});

it('does not create a second conversion on duplicate postback', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    getJson(postbackUrl($trackingLink, ['external_id' => 'TXN-DUP-002']));
    getJson(postbackUrl($trackingLink, ['external_id' => 'TXN-DUP-002']));

    assertDatabaseCount('conversions', 1);
    assertDatabaseHas('conversions', [
        'external_id' => 'TXN-DUP-002',
        'campaign_id' => $campaign->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated API Still Returns 409
|--------------------------------------------------------------------------
*/

it('authenticated API still returns 409 for duplicate external_id', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-AUTH-DUP']
    )->assertCreated();

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-AUTH-DUP']
    )->assertStatus(409);
});

/*
|--------------------------------------------------------------------------
| Cross-Campaign Security
|--------------------------------------------------------------------------
*/

it('postback for one tracking link cannot create conversion on unrelated campaign', function (): void {
    $user = User::factory()->create();
    $offerA = Offer::factory()->for($user)->create();
    $campaignA = Campaign::factory()->for($offerA)->create(['status' => CampaignStatus::Active]);
    $linkA = TrackingLink::factory()->for($campaignA)->create();

    $offerB = Offer::factory()->for($user)->create();
    $campaignB = Campaign::factory()->for($offerB)->create(['status' => CampaignStatus::Active]);

    getJson(postbackUrl($linkA, ['external_id' => 'TXN-CROSS']))->assertOk();

    assertDatabaseHas('conversions', [
        'campaign_id' => $campaignA->id,
        'external_id' => 'TXN-CROSS',
    ]);
    assertDatabaseMissing('conversions', [
        'campaign_id' => $campaignB->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Endpoint Without Auth
|--------------------------------------------------------------------------
*/

it('works without authenticated user', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    $response = getJson(postbackUrl($trackingLink));

    $response->assertOk();

    assertDatabaseHas('conversions', [
        'campaign_id' => $campaign->id,
        'external_id' => 'TXN-001',
    ]);
});

/*
|--------------------------------------------------------------------------
| PostbackSigner Unit Tests
|--------------------------------------------------------------------------
*/

it('generates a deterministic token for the same code', function (): void {
    $signer = new PostbackSigner;

    $token1 = $signer->tokenFor('ABC123');
    $token2 = $signer->tokenFor('ABC123');

    expect($token1)->toBe($token2)
        ->and($token1)->toBeString()
        ->and(strlen($token1))->toBe(64);
});

it('generates different tokens for different codes', function (): void {
    $signer = new PostbackSigner;

    $token1 = $signer->tokenFor('CODE_A');
    $token2 = $signer->tokenFor('CODE_B');

    expect($token1)->not->toBe($token2);
});

it('validates correct token', function (): void {
    $signer = new PostbackSigner;

    $code = 'TESTCODE';
    $token = $signer->tokenFor($code);

    expect($signer->isValid($code, $token))->toBeTrue();
});

it('rejects incorrect token', function (): void {
    $signer = new PostbackSigner;

    expect($signer->isValid('TESTCODE', 'wrong-token'))->toBeFalse();
});

it('does not expose APP_KEY in token', function (): void {
    $signer = new PostbackSigner;
    $appKey = config('app.key');

    $token = $signer->tokenFor('some-code');

    expect($token)->not->toContain($appKey)
        ->and(str_contains($token, substr($appKey, 0, 10)))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Campaign Show View Postback URL
|--------------------------------------------------------------------------
*/

it('campaign conversions tab contains postback URL section', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $trackingLink = TrackingLink::factory()->for($campaign)->create();

    Sanctum::actingAs($user);

    $response = $this->get(route('campaigns.show', $campaign));

    $response->assertOk()
        ->assertSee('Conversion Postback')
        ->assertSee('Give this URL to your affiliate network')
        ->assertSee('/postback/'.$trackingLink->code);
});

it('campaign conversions tab does not expose APP_KEY', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    TrackingLink::factory()->for($campaign)->create();

    Sanctum::actingAs($user);

    $response = $this->get(route('campaigns.show', $campaign));

    $response->assertOk()
        ->assertDontSee(config('app.key'));
});

it('campaign with no tracking links shows generate message', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);

    Sanctum::actingAs($user);

    $response = $this->get(route('campaigns.show', $campaign));

    $response->assertOk()
        ->assertSee('Generate a tracking link first');
});

/*
|--------------------------------------------------------------------------
| Empty State Wording
|--------------------------------------------------------------------------
*/

it('conversions empty state reflects real postback flow', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);

    Sanctum::actingAs($user);

    $response = $this->get(route('campaigns.show', $campaign));

    $response->assertOk()
        ->assertSee('Conversions reported by your affiliate network through the campaign postback URL will appear here for review.');
});
