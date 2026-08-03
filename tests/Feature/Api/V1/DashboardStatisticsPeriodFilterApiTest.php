<?php

use App\DTOs\DashboardStatisticsPeriod;
use App\Enums\CampaignStatus;
use App\Enums\ConversionStatus;
use App\Models\Campaign;
use App\Models\CampaignExpense;
use App\Models\Conversion;
use App\Models\Offer;
use App\Models\TrackingClick;
use App\Models\TrackingLink;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/*
|--------------------------------------------------------------------------
| Backward Compatibility
|--------------------------------------------------------------------------
*/

it('returns same all-time statistics without parameters', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '50.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '50.00',
    ]);
    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '20.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.offer_count', 1)
        ->assertJsonPath('data.statistics.campaign_count', 1)
        ->assertJsonPath('data.statistics.active_campaign_count', 1)
        ->assertJsonPath('data.statistics.revenue', '50.00')
        ->assertJsonPath('data.statistics.total_expenses', '20.00')
        ->assertJsonPath('data.statistics.profit', '30.00');
});

/*
|--------------------------------------------------------------------------
| Predefined Period Validation
|--------------------------------------------------------------------------
*/

it('accepts today period without from/to', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', ['period' => 'today']));

    $response->assertOk();
});

it('accepts last_7_days period without from/to', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', ['period' => 'last_7_days']));

    $response->assertOk();
});

it('accepts last_30_days period without from/to', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', ['period' => 'last_30_days']));

    $response->assertOk();
});

it('accepts this_month period without from/to', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', ['period' => 'this_month']));

    $response->assertOk();
});

it('returns 422 for invalid period', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', ['period' => 'invalid']));

    $response->assertUnprocessable();
});

/*
|--------------------------------------------------------------------------
| Custom Period Validation
|--------------------------------------------------------------------------
*/

it('returns 422 for custom without from/to', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', ['period' => 'custom']));

    $response->assertUnprocessable();
});

it('returns 422 for custom missing from', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'custom',
        'to' => '2026-08-03',
    ]));

    $response->assertUnprocessable();
});

it('returns 422 for custom missing to', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'custom',
        'from' => '2026-08-01',
    ]));

    $response->assertUnprocessable();
});

it('returns 422 when from > to', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'custom',
        'from' => '2026-08-03',
        'to' => '2026-08-01',
    ]));

    $response->assertUnprocessable();
});

it('returns 422 for invalid date format', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'custom',
        'from' => 'not-a-date',
        'to' => '2026-08-03',
    ]));

    $response->assertUnprocessable();
});

it('returns 422 for future from date', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'custom',
        'from' => '2099-01-01',
        'to' => '2099-12-31',
    ]));

    $response->assertUnprocessable();
});

it('returns 422 for future to date', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'custom',
        'from' => '2026-08-01',
        'to' => '2099-12-31',
    ]));

    $response->assertUnprocessable();
});

it('returns 422 for from/to without custom period', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'from' => '2026-08-01',
        'to' => '2026-08-03',
    ]));

    $response->assertUnprocessable();
});

it('returns 422 for from/to with predefined period', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
        'from' => '2026-08-01',
        'to' => '2026-08-03',
    ]));

    $response->assertUnprocessable();
});

/*
|--------------------------------------------------------------------------
| Click Count Period Filtering
|--------------------------------------------------------------------------
*/

it('includes click inside range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);
    $link = TrackingLink::factory()->create(['campaign_id' => $campaign->id]);

    $now = Carbon::now('UTC');
    TrackingClick::factory()->create([
        'tracking_link_id' => $link->id,
        'created_at' => $now,
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 1);
});

it('excludes click before range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);
    $link = TrackingLink::factory()->create(['campaign_id' => $campaign->id]);

    TrackingClick::factory()->create([
        'tracking_link_id' => $link->id,
        'created_at' => Carbon::now('UTC')->subDays(10),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 0);
});

it('excludes click after range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);
    $link = TrackingLink::factory()->create(['campaign_id' => $campaign->id]);

    TrackingClick::factory()->create([
        'tracking_link_id' => $link->id,
        'created_at' => Carbon::now('UTC')->addDays(5),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 0);
});

/*
|--------------------------------------------------------------------------
| Conversion Count Period Filtering
|--------------------------------------------------------------------------
*/

it('includes conversion with converted_at inside range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Pending,
        'converted_at' => Carbon::now('UTC'),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1);
});

it('excludes conversion with converted_at outside range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Pending,
        'converted_at' => Carbon::now('UTC')->subDays(10),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 0);
});

it('excludes conversion when created_at inside but converted_at outside', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Pending,
        'converted_at' => Carbon::now('UTC')->subDays(10),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 0);
});

it('pending conversion inside period increments count but not revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '50.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Pending,
        'revenue' => '50.00',
        'converted_at' => Carbon::now('UTC'),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '0.00');
});

it('rejected conversion inside period increments count but not revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '50.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Rejected,
        'revenue' => '50.00',
        'converted_at' => Carbon::now('UTC'),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '0.00');
});

it('approved conversion inside period contributes count and revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '75.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '75.00',
        'converted_at' => Carbon::now('UTC'),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '75.00');
});

it('approved conversion outside period contributes neither count nor revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '100.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '100.00',
        'converted_at' => Carbon::now('UTC')->subDays(10),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 0)
        ->assertJsonPath('data.statistics.revenue', '0.00');
});

/*
|--------------------------------------------------------------------------
| Expense Period Filtering
|--------------------------------------------------------------------------
*/

it('includes expense with spent_at inside range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);

    CampaignExpense::factory()->forCampaign($campaign)->create([
        'amount' => '50.00',
        'spent_at' => Carbon::now('UTC')->toDateString(),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.total_expenses', '50.00');
});

it('excludes expense with spent_at outside range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);

    CampaignExpense::factory()->forCampaign($campaign)->create([
        'amount' => '50.00',
        'spent_at' => Carbon::now('UTC')->subDays(10)->toDateString(),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.total_expenses', '0.00');
});

it('filters expense by spent_at not created_at', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);

    CampaignExpense::factory()->forCampaign($campaign)->create([
        'amount' => '50.00',
        'spent_at' => Carbon::now('UTC')->subDays(10)->toDateString(),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.total_expenses', '0.00');
});

/*
|--------------------------------------------------------------------------
| Profit
|--------------------------------------------------------------------------
*/

it('computes period profit as period revenue minus period expenses', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '100.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '100.00',
        'converted_at' => Carbon::now('UTC'),
    ]);
    CampaignExpense::factory()->forCampaign($campaign)->create([
        'amount' => '60.00',
        'spent_at' => Carbon::now('UTC')->toDateString(),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.revenue', '100.00')
        ->assertJsonPath('data.statistics.total_expenses', '60.00')
        ->assertJsonPath('data.statistics.profit', '40.00');
});

it('supports negative period profit', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '30.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '30.00',
        'converted_at' => Carbon::now('UTC'),
    ]);
    CampaignExpense::factory()->forCampaign($campaign)->create([
        'amount' => '100.00',
        'spent_at' => Carbon::now('UTC')->toDateString(),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.profit', '-70.00');
});

/*
|--------------------------------------------------------------------------
| Inventory Metrics Unchanged by Period
|--------------------------------------------------------------------------
*/

it('offer_count unchanged by empty period', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Offer::factory()->count(3)->create(['user_id' => $user->id]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.offer_count', 3);
});

it('campaign_count unchanged by empty period', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    Campaign::factory()->count(2)->create(['offer_id' => $offer->id]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.campaign_count', 2);
});

it('active_campaign_count unchanged by empty period', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);
    Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Draft]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.active_campaign_count', 1);
});

/*
|--------------------------------------------------------------------------
| Boundary Tests
|--------------------------------------------------------------------------
*/

it('includes record at exact period start', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);
    $link = TrackingLink::factory()->create(['campaign_id' => $campaign->id]);

    $today = Carbon::now('UTC')->startOfDay();
    TrackingClick::factory()->create([
        'tracking_link_id' => $link->id,
        'created_at' => $today,
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 1);
});

it('excludes record at exact endExclusive boundary', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);
    $link = TrackingLink::factory()->create(['campaign_id' => $campaign->id]);

    $tomorrow = Carbon::now('UTC')->startOfDay()->addDay();
    TrackingClick::factory()->create([
        'tracking_link_id' => $link->id,
        'created_at' => $tomorrow,
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 0);
});

it('today uses application timezone', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $tz = config('app.timezone');
    $now = Carbon::now($tz);

    $period = DashboardStatisticsPeriod::today();

    expect($period->start->timezone->getName())->toBe($tz)
        ->and($period->start->hour)->toBe(0)
        ->and($period->start->minute)->toBe(0)
        ->and($period->start->second)->toBe(0);
});

it('last_7_days means today plus previous 6 calendar days', function (): void {
    $period = DashboardStatisticsPeriod::last7Days();

    $daysDiff = (int) $period->start->diffInDays($period->endExclusive->subDay());

    expect($daysDiff)->toBe(6);
});

it('last_30_days means today plus previous 29 calendar days', function (): void {
    $period = DashboardStatisticsPeriod::last30Days();

    $daysDiff = (int) $period->start->diffInDays($period->endExclusive->subDay());

    expect($daysDiff)->toBe(29);
});

it('this_month means first of month through today', function (): void {
    $period = DashboardStatisticsPeriod::thisMonth();

    expect($period->start->day)->toBe(1)
        ->and($period->start->hour)->toBe(0)
        ->and($period->start->minute)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Empty Period
|--------------------------------------------------------------------------
*/

it('returns zero event metrics for valid period with no activity', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Offer::factory()->count(2)->create(['user_id' => $user->id]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 0)
        ->assertJsonPath('data.statistics.conversion_count', 0)
        ->assertJsonPath('data.statistics.revenue', '0.00')
        ->assertJsonPath('data.statistics.total_expenses', '0.00')
        ->assertJsonPath('data.statistics.profit', '0.00')
        ->assertJsonPath('data.statistics.offer_count', 2);
});

/*
|--------------------------------------------------------------------------
| Ownership Isolation
|--------------------------------------------------------------------------
*/

it('excludes foreign data in same selected dates', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    $offerA = Offer::factory()->create(['user_id' => $userA->id]);
    $offerB = Offer::factory()->create(['user_id' => $userB->id]);

    $campaignA = Campaign::factory()->create(['offer_id' => $offerA->id]);
    $campaignB = Campaign::factory()->create(['offer_id' => $offerB->id]);

    $linkA = TrackingLink::factory()->create(['campaign_id' => $campaignA->id]);
    $linkB = TrackingLink::factory()->create(['campaign_id' => $campaignB->id]);

    TrackingClick::factory()->count(3)->create([
        'tracking_link_id' => $linkA->id,
        'created_at' => Carbon::now('UTC'),
    ]);
    TrackingClick::factory()->count(5)->create([
        'tracking_link_id' => $linkB->id,
        'created_at' => Carbon::now('UTC'),
    ]);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 3);
});

/*
|--------------------------------------------------------------------------
| API Security
|--------------------------------------------------------------------------
*/

it('returns 401 for guest with period param', function (): void {
    $response = getJson(route('api.v1.dashboard.statistics', ['period' => 'today']));

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Performance
|--------------------------------------------------------------------------
*/

it('performs bounded number of queries with period filter', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);
    $link = TrackingLink::factory()->create(['campaign_id' => $campaign->id]);
    TrackingClick::factory()->count(5)->create(['tracking_link_id' => $link->id, 'created_at' => Carbon::now('UTC')]);
    Conversion::factory()->forCampaign($campaign)->create(['status' => ConversionStatus::Approved, 'revenue' => '25.00', 'converted_at' => Carbon::now('UTC')]);
    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '10.00', 'spent_at' => Carbon::now('UTC')->toDateString()]);

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    getJson(route('api.v1.dashboard.statistics', ['period' => 'today']));

    expect($queryCount)->toBeLessThanOrEqual(10);
});

/*
|--------------------------------------------------------------------------
| Custom Range
|--------------------------------------------------------------------------
*/

it('accepts valid custom range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'custom',
        'from' => '2026-08-01',
        'to' => '2026-08-03',
    ]));

    $response->assertOk();
});

it('accepts same day custom range', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics', [
        'period' => 'custom',
        'from' => '2026-08-01',
        'to' => '2026-08-01',
    ]));

    $response->assertOk();
});

/*
|--------------------------------------------------------------------------
| DashboardStatisticsPeriod Unit Tests
|--------------------------------------------------------------------------
*/

it('allTime returns null start and end', function (): void {
    $period = DashboardStatisticsPeriod::allTime();

    expect($period->start)->toBeNull()
        ->and($period->endExclusive)->toBeNull()
        ->and($period->selectedPeriod)->toBeNull()
        ->and($period->isAllTime())->toBeTrue();
});

it('today returns correct boundaries', function (): void {
    $period = DashboardStatisticsPeriod::today();

    expect($period->isAllTime())->toBeFalse()
        ->and($period->selectedPeriod)->toBe('today')
        ->and($period->start->hour)->toBe(0)
        ->and($period->start->minute)->toBe(0)
        ->and($period->start->diffInHours($period->endExclusive))->toBe(24.0);
});

it('custom returns correct boundaries', function (): void {
    $from = CarbonImmutable::parse('2026-08-01');
    $to = CarbonImmutable::parse('2026-08-03');

    $period = DashboardStatisticsPeriod::custom($from, $to);

    expect($period->start->toDateString())->toBe('2026-08-01')
        ->and($period->endExclusive->toDateString())->toBe('2026-08-04')
        ->and($period->selectedPeriod)->toBe('custom');
});

/*
|--------------------------------------------------------------------------
| Regression: KAN-18 Backward Compatibility
|--------------------------------------------------------------------------
*/

it('preserves all-time revenue approved-only rule', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '100.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Pending,
        'revenue' => '100.00',
    ]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '0.00');
});

it('preserves all-time conversion count all statuses', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create(['status' => ConversionStatus::Pending]);
    Conversion::factory()->forCampaign($campaign)->create(['status' => ConversionStatus::Approved]);
    Conversion::factory()->forCampaign($campaign)->create(['status' => ConversionStatus::Rejected]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 3);
});

it('preserves all-time profit formula', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '100.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '100.00',
    ]);
    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '60.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.profit', '40.00');
});
