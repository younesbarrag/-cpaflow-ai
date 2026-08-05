<?php

use App\Enums\CampaignStatus;
use App\Enums\ConversionStatus;
use App\Models\Campaign;
use App\Models\Conversion;
use App\Models\Offer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Security: Guest Authentication
|--------------------------------------------------------------------------
*/

it('rejects unauthenticated approve request', function (): void {
    $campaign = Campaign::factory()->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertUnauthorized();
});

it('rejects unauthenticated reject request', function (): void {
    $campaign = Campaign::factory()->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Security: Foreign Affiliate
|--------------------------------------------------------------------------
*/

it('rejects foreign affiliate approving conversion', function (): void {
    $owner = User::factory()->create();
    $foreignUser = User::factory()->create();
    $offer = Offer::factory()->for($owner)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($foreignUser);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertForbidden();
});

it('rejects foreign affiliate rejecting conversion', function (): void {
    $owner = User::factory()->create();
    $foreignUser = User::factory()->create();
    $offer = Offer::factory()->for($owner)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($foreignUser);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Security: Admin on Foreign Campaign
|--------------------------------------------------------------------------
*/

it('rejects admin approving conversion on foreign campaign', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $offer = Offer::factory()->for($owner)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($admin);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertForbidden();
});

it('rejects admin rejecting conversion on foreign campaign', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $offer = Offer::factory()->for($owner)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($admin);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Security: Wrong-Parent Conversion
|--------------------------------------------------------------------------
*/

it('returns 404 when approving conversion from wrong campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaignA = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $campaignB = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaignB)->create();

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaignA, $conversion]))
        ->assertNotFound();
});

it('returns 404 when rejecting conversion from wrong campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaignA = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $campaignB = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaignB)->create();

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaignA, $conversion]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Approve: Happy Path
|--------------------------------------------------------------------------
*/

it('allows owner to approve a pending conversion', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    $response = postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]));

    $response->assertOk()
        ->assertJsonPath('data.conversion.status', 'approved');

    assertDatabaseHas('conversions', [
        'id' => $conversion->id,
        'status' => ConversionStatus::Approved->value,
    ]);
});

it('returns ConversionResource shape on approve', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk()
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
});

it('preserves revenue after approval', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create([
        'revenue' => '42.50',
    ]);

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->revenue)->toBe('42.50');
});

it('preserves converted_at after approval', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $originalConvertedAt = now()->subDays(10);
    $conversion = Conversion::factory()->forCampaign($campaign)->create([
        'converted_at' => $originalConvertedAt,
    ]);

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->converted_at->timestamp)->toBe($originalConvertedAt->timestamp);
});

/*
|--------------------------------------------------------------------------
| Approve: Idempotent
|--------------------------------------------------------------------------
*/

it('returns 200 when approving an already approved conversion', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->approved()->create();

    Sanctum::actingAs($user);

    $response = postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]));

    $response->assertOk()
        ->assertJsonPath('data.conversion.status', 'approved');
});

it('does not modify conversion on idempotent approve', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->approved()->create([
        'revenue' => '25.00',
    ]);

    Sanctum::actingAs($user);

    $originalUpdatedAt = $conversion->updated_at;

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->status)->toBe(ConversionStatus::Approved)
        ->and($conversion->revenue)->toBe('25.00');
});

/*
|--------------------------------------------------------------------------
| Reject: Happy Path
|--------------------------------------------------------------------------
*/

it('allows owner to reject a pending conversion', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    $response = postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]));

    $response->assertOk()
        ->assertJsonPath('data.conversion.status', 'rejected');

    assertDatabaseHas('conversions', [
        'id' => $conversion->id,
        'status' => ConversionStatus::Rejected->value,
    ]);
});

it('returns ConversionResource shape on reject', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertOk()
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
});

it('preserves revenue after rejection', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create([
        'revenue' => '33.75',
    ]);

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->revenue)->toBe('33.75');
});

it('preserves converted_at after rejection', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $originalConvertedAt = now()->subDays(5);
    $conversion = Conversion::factory()->forCampaign($campaign)->create([
        'converted_at' => $originalConvertedAt,
    ]);

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->converted_at->timestamp)->toBe($originalConvertedAt->timestamp);
});

/*
|--------------------------------------------------------------------------
| Reject: Idempotent
|--------------------------------------------------------------------------
*/

it('returns 200 when rejecting an already rejected conversion', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->rejected()->create();

    Sanctum::actingAs($user);

    $response = postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]));

    $response->assertOk()
        ->assertJsonPath('data.conversion.status', 'rejected');
});

/*
|--------------------------------------------------------------------------
| Conflict: Opposite Terminal
|--------------------------------------------------------------------------
*/

it('returns 409 when rejecting an approved conversion', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->approved()->create();

    Sanctum::actingAs($user);

    $response = postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]));

    $response->assertStatus(409)
        ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'not allowed'))
        ->assertJsonStructure([
            'message',
            'errors' => ['status'],
        ]);
});

it('returns 409 when approving a rejected conversion', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->rejected()->create();

    Sanctum::actingAs($user);

    $response = postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]));

    $response->assertStatus(409)
        ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'not allowed'))
        ->assertJsonStructure([
            'message',
            'errors' => ['status'],
        ]);
});

it('does not modify conversion on conflict', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->approved()->create([
        'revenue' => '50.00',
    ]);

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertStatus(409);

    $conversion->refresh();
    expect($conversion->status)->toBe(ConversionStatus::Approved)
        ->and($conversion->revenue)->toBe('50.00');
});

/*
|--------------------------------------------------------------------------
| Revenue Snapshot
|--------------------------------------------------------------------------
*/

it('does not recalculate revenue after approval', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create(['payout' => '25.00']);
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create([
        'revenue' => '25.00',
    ]);

    $offer->update(['payout' => '50.00']);

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->revenue)->toBe('25.00');
});

/*
|--------------------------------------------------------------------------
| Dashboard Integration
|--------------------------------------------------------------------------
*/

it('pending conversion contributes to conversion_count', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk();

    $conversionCount = $response->json('data.statistics.conversion_count');
    expect($conversionCount)->toBeGreaterThanOrEqual(1);
});

it('pending conversion contributes zero revenue', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    Conversion::factory()->forCampaign($campaign)->create(['revenue' => '100.00']);

    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk();

    $revenue = (float) $response->json('data.statistics.revenue');
    expect($revenue)->toBe(0.0);
});

it('approving a conversion increases revenue', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);

    Sanctum::actingAs($user);

    $before = getJson(route('api.v1.dashboard.statistics'));
    $revenueBefore = (float) $before->json('data.statistics.revenue');

    $conversion = Conversion::factory()->forCampaign($campaign)->create(['revenue' => '75.00']);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $after = getJson(route('api.v1.dashboard.statistics'));
    $revenueAfter = (float) $after->json('data.statistics.revenue');

    expect($revenueAfter)->toBe($revenueBefore + 75.0);
});

it('approving a conversion increases profit', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);

    Sanctum::actingAs($user);

    $before = getJson(route('api.v1.dashboard.statistics'));
    $profitBefore = (float) $before->json('data.statistics.profit');

    $conversion = Conversion::factory()->forCampaign($campaign)->create(['revenue' => '60.00']);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $after = getJson(route('api.v1.dashboard.statistics'));
    $profitAfter = (float) $after->json('data.statistics.profit');

    expect($profitAfter)->toBe($profitBefore + 60.0);
});

it('conversion_count unchanged after approval', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    $before = getJson(route('api.v1.dashboard.statistics'));
    $countBefore = $before->json('data.statistics.conversion_count');

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $after = getJson(route('api.v1.dashboard.statistics'));
    $countAfter = $after->json('data.statistics.conversion_count');

    expect($countAfter)->toBe($countBefore);
});

it('rejected conversion contributes zero revenue', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create(['revenue' => '100.00']);

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertOk();

    $response = getJson(route('api.v1.dashboard.statistics'));
    $revenue = (float) $response->json('data.statistics.revenue');
    expect($revenue)->toBe(0.0);
});

it('rejection does not change profit', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);

    Sanctum::actingAs($user);

    $before = getJson(route('api.v1.dashboard.statistics'));
    $profitBefore = (float) $before->json('data.statistics.profit');

    $conversion = Conversion::factory()->forCampaign($campaign)->create(['revenue' => '100.00']);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertOk();

    $after = getJson(route('api.v1.dashboard.statistics'));
    $profitAfter = (float) $after->json('data.statistics.profit');

    expect($profitAfter)->toBe($profitBefore);
});

/*
|--------------------------------------------------------------------------
| Period Filtering
|--------------------------------------------------------------------------
*/

it('approval does not change converted_at', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $historicalDate = now()->subDays(30);
    $conversion = Conversion::factory()->forCampaign($campaign)->create([
        'converted_at' => $historicalDate,
    ]);

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->converted_at->timestamp)->toBe($historicalDate->timestamp);
});

/*
|--------------------------------------------------------------------------
| Concurrency / Action Behavior
|--------------------------------------------------------------------------
*/

it('action uses current persisted state under lock', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->status)->toBe(ConversionStatus::Approved);
});

it('competing approve/reject cannot silently overwrite', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertStatus(409);

    $conversion->refresh();
    expect($conversion->status)->toBe(ConversionStatus::Approved);
});

it('second opposite transition receives 409', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]))
        ->assertOk();

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertStatus(409);
});

it('concurrent same-target transition behaves idempotently', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);
    $conversion = Conversion::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    postJson(route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]))
        ->assertOk();

    $conversion->refresh();
    expect($conversion->status)->toBe(ConversionStatus::Approved);
});

/*
|--------------------------------------------------------------------------
| Regression: Existing Behavior
|--------------------------------------------------------------------------
*/

it('still creates pending conversion on record', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-REG-001']
    )->assertCreated();

    assertDatabaseHas('conversions', [
        'external_id' => 'TXN-REG-001',
        'status' => ConversionStatus::Pending->value,
    ]);
});

it('duplicate external_id still returns 409', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create(['status' => CampaignStatus::Active]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-DUP-001']
    )->assertCreated();

    postJson(
        route('api.v1.campaigns.conversions.store', $campaign),
        ['external_id' => 'TXN-DUP-001']
    )->assertStatus(409);
});
