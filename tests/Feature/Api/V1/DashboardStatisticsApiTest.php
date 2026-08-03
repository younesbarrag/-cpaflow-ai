<?php

use App\Enums\CampaignStatus;
use App\Enums\ConversionStatus;
use App\Models\Campaign;
use App\Models\CampaignExpense;
use App\Models\Conversion;
use App\Models\Offer;
use App\Models\TrackingClick;
use App\Models\TrackingLink;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/*
|--------------------------------------------------------------------------
| Empty State
|--------------------------------------------------------------------------
*/

it('returns 200 for authenticated user', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk();
});

it('returns all zeros for new user with no data', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.offer_count', 0)
        ->assertJsonPath('data.statistics.campaign_count', 0)
        ->assertJsonPath('data.statistics.active_campaign_count', 0)
        ->assertJsonPath('data.statistics.click_count', 0)
        ->assertJsonPath('data.statistics.conversion_count', 0)
        ->assertJsonPath('data.statistics.revenue', '0.00')
        ->assertJsonPath('data.statistics.total_expenses', '0.00')
        ->assertJsonPath('data.statistics.profit', '0.00');
});

/*
|--------------------------------------------------------------------------
| Offer Count
|--------------------------------------------------------------------------
*/

it('counts owned offers', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Offer::factory()->count(3)->create(['user_id' => $user->id]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.offer_count', 3);
});

/*
|--------------------------------------------------------------------------
| Campaign Count
|--------------------------------------------------------------------------
*/

it('counts all owned campaigns', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    Campaign::factory()->count(3)->create(['offer_id' => $offer->id]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.campaign_count', 3);
});

/*
|--------------------------------------------------------------------------
| Active Campaign Count
|--------------------------------------------------------------------------
*/

it('counts only active campaigns', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);
    Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);
    Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Draft]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.campaign_count', 3)
        ->assertJsonPath('data.statistics.active_campaign_count', 2);
});

/*
|--------------------------------------------------------------------------
| Click Count
|--------------------------------------------------------------------------
*/

it('counts tracking clicks, not tracking links', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);
    $trackingLink = TrackingLink::factory()->create(['campaign_id' => $campaign->id]);

    TrackingClick::factory()->count(5)->create(['tracking_link_id' => $trackingLink->id]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 5);
});

/*
|--------------------------------------------------------------------------
| Conversion Count
|--------------------------------------------------------------------------
*/

it('counts all conversions regardless of status', function (): void {
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

/*
|--------------------------------------------------------------------------
| Revenue Status Rule
|--------------------------------------------------------------------------
*/

it('counts pending conversion as zero revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '50.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Pending,
        'revenue' => '50.00',
    ]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '0.00');
});

it('counts rejected conversion as zero revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '75.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Rejected,
        'revenue' => '75.00',
    ]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '0.00');
});

it('counts approved conversion revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '25.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '25.00',
    ]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '25.00');
});

it('sums multiple approved conversions correctly', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '10.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '10.00',
    ]);
    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '20.00',
    ]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 2)
        ->assertJsonPath('data.statistics.revenue', '30.00');
});

it('does not change historical revenue when offer payout changes', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '100.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '100.00',
    ]);

    $offer->update(['payout' => '200.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.revenue', '100.00');
});

/*
|--------------------------------------------------------------------------
| Expenses
|--------------------------------------------------------------------------
*/

it('sums campaign expense amounts correctly', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);

    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '50.00']);
    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '75.25']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.total_expenses', '125.25');
});

it('does not count campaign budget as expense', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    Campaign::factory()->create(['offer_id' => $offer->id, 'budget' => '1000.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.total_expenses', '0.00');
});

/*
|--------------------------------------------------------------------------
| Profit
|--------------------------------------------------------------------------
*/

it('computes profit as revenue minus expenses', function (): void {
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
        ->assertJsonPath('data.statistics.revenue', '100.00')
        ->assertJsonPath('data.statistics.total_expenses', '60.00')
        ->assertJsonPath('data.statistics.profit', '40.00');
});

it('returns negative profit when expenses exceed revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '30.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '30.00',
    ]);
    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '100.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.profit', '-70.00');
});

it('returns negative profit with zero revenue and expenses', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id]);

    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '50.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.revenue', '0.00')
        ->assertJsonPath('data.statistics.total_expenses', '50.00')
        ->assertJsonPath('data.statistics.profit', '-50.00');
});

it('returns positive profit with zero expenses and approved revenue', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '200.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '200.00',
    ]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.revenue', '200.00')
        ->assertJsonPath('data.statistics.total_expenses', '0.00')
        ->assertJsonPath('data.statistics.profit', '200.00');
});

/*
|--------------------------------------------------------------------------
| Ownership Isolation
|--------------------------------------------------------------------------
*/

it('excludes foreign user offers from offer count', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    Offer::factory()->count(3)->create(['user_id' => $userA->id]);
    Offer::factory()->count(5)->create(['user_id' => $userB->id]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.offer_count', 3);
});

it('excludes foreign user campaigns from campaign count', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    $offerA = Offer::factory()->create(['user_id' => $userA->id]);
    $offerB = Offer::factory()->create(['user_id' => $userB->id]);

    Campaign::factory()->count(2)->create(['offer_id' => $offerA->id]);
    Campaign::factory()->count(4)->create(['offer_id' => $offerB->id]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.campaign_count', 2);
});

it('excludes foreign user clicks from click count', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    $offerA = Offer::factory()->create(['user_id' => $userA->id]);
    $offerB = Offer::factory()->create(['user_id' => $userB->id]);

    $campaignA = Campaign::factory()->create(['offer_id' => $offerA->id]);
    $campaignB = Campaign::factory()->create(['offer_id' => $offerB->id]);

    $linkA = TrackingLink::factory()->create(['campaign_id' => $campaignA->id]);
    $linkB = TrackingLink::factory()->create(['campaign_id' => $campaignB->id]);

    TrackingClick::factory()->count(5)->create(['tracking_link_id' => $linkA->id]);
    TrackingClick::factory()->count(10)->create(['tracking_link_id' => $linkB->id]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 5);
});

it('excludes foreign user conversions from conversion count', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    $offerA = Offer::factory()->create(['user_id' => $userA->id]);
    $offerB = Offer::factory()->create(['user_id' => $userB->id]);

    $campaignA = Campaign::factory()->create(['offer_id' => $offerA->id, 'status' => CampaignStatus::Active]);
    $campaignB = Campaign::factory()->create(['offer_id' => $offerB->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaignA)->create(['status' => ConversionStatus::Approved, 'revenue' => '50.00']);
    Conversion::factory()->forCampaign($campaignB)->create(['status' => ConversionStatus::Approved, 'revenue' => '200.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '50.00');
});

it('excludes foreign user approved revenue from revenue', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    $offerA = Offer::factory()->create(['user_id' => $userA->id]);
    $offerB = Offer::factory()->create(['user_id' => $userB->id]);

    $campaignA = Campaign::factory()->create(['offer_id' => $offerA->id, 'status' => CampaignStatus::Active]);
    $campaignB = Campaign::factory()->create(['offer_id' => $offerB->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaignA)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '100.00',
    ]);
    Conversion::factory()->forCampaign($campaignB)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '500.00',
    ]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.revenue', '100.00');
});

it('excludes foreign user expenses from total expenses', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    $offerA = Offer::factory()->create(['user_id' => $userA->id]);
    $offerB = Offer::factory()->create(['user_id' => $userB->id]);

    $campaignA = Campaign::factory()->create(['offer_id' => $offerA->id]);
    $campaignB = Campaign::factory()->create(['offer_id' => $offerB->id]);

    CampaignExpense::factory()->forCampaign($campaignA)->create(['amount' => '30.00']);
    CampaignExpense::factory()->forCampaign($campaignB)->create(['amount' => '200.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.total_expenses', '30.00');
});

it('excludes foreign user profit from profit calculation', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    $offerA = Offer::factory()->create(['user_id' => $userA->id]);
    $offerB = Offer::factory()->create(['user_id' => $userB->id]);

    $campaignA = Campaign::factory()->create(['offer_id' => $offerA->id, 'status' => CampaignStatus::Active]);
    $campaignB = Campaign::factory()->create(['offer_id' => $offerB->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaignA)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '100.00',
    ]);
    CampaignExpense::factory()->forCampaign($campaignA)->create(['amount' => '40.00']);

    Conversion::factory()->forCampaign($campaignB)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '1000.00',
    ]);
    CampaignExpense::factory()->forCampaign($campaignB)->create(['amount' => '50.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.profit', '60.00');
});

it('returns isolated metrics when both users have significant data', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA);

    $offerA = Offer::factory()->create(['user_id' => $userA->id, 'payout' => '25.00']);
    $offerB = Offer::factory()->create(['user_id' => $userB->id, 'payout' => '50.00']);

    $campaignA = Campaign::factory()->create(['offer_id' => $offerA->id, 'status' => CampaignStatus::Active]);
    $campaignB = Campaign::factory()->create(['offer_id' => $offerB->id, 'status' => CampaignStatus::Active]);

    $linkA = TrackingLink::factory()->create(['campaign_id' => $campaignA->id]);
    $linkB = TrackingLink::factory()->create(['campaign_id' => $campaignB->id]);

    TrackingClick::factory()->count(10)->create(['tracking_link_id' => $linkA->id]);
    TrackingClick::factory()->count(20)->create(['tracking_link_id' => $linkB->id]);

    Conversion::factory()->forCampaign($campaignA)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '25.00',
    ]);
    Conversion::factory()->forCampaign($campaignB)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '50.00',
    ]);

    CampaignExpense::factory()->forCampaign($campaignA)->create(['amount' => '10.00']);
    CampaignExpense::factory()->forCampaign($campaignB)->create(['amount' => '30.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.offer_count', 1)
        ->assertJsonPath('data.statistics.campaign_count', 1)
        ->assertJsonPath('data.statistics.active_campaign_count', 1)
        ->assertJsonPath('data.statistics.click_count', 10)
        ->assertJsonPath('data.statistics.conversion_count', 1)
        ->assertJsonPath('data.statistics.revenue', '25.00')
        ->assertJsonPath('data.statistics.total_expenses', '10.00')
        ->assertJsonPath('data.statistics.profit', '15.00');
});

/*
|--------------------------------------------------------------------------
| Multiple Parent Entities
|--------------------------------------------------------------------------
*/

it('aggregates data from multiple offers correctly', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer1 = Offer::factory()->create(['user_id' => $user->id, 'payout' => '10.00']);
    $offer2 = Offer::factory()->create(['user_id' => $user->id, 'payout' => '20.00']);

    $campaign1 = Campaign::factory()->create(['offer_id' => $offer1->id, 'status' => CampaignStatus::Active]);
    $campaign2 = Campaign::factory()->create(['offer_id' => $offer2->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign1)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '10.00',
    ]);
    Conversion::factory()->forCampaign($campaign2)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '20.00',
    ]);

    CampaignExpense::factory()->forCampaign($campaign1)->create(['amount' => '5.00']);
    CampaignExpense::factory()->forCampaign($campaign2)->create(['amount' => '8.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.offer_count', 2)
        ->assertJsonPath('data.statistics.campaign_count', 2)
        ->assertJsonPath('data.statistics.active_campaign_count', 2)
        ->assertJsonPath('data.statistics.revenue', '30.00')
        ->assertJsonPath('data.statistics.total_expenses', '13.00')
        ->assertJsonPath('data.statistics.profit', '17.00');
});

it('aggregates data from multiple campaigns correctly', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '15.00']);

    $campaign1 = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);
    $campaign2 = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    $link1 = TrackingLink::factory()->create(['campaign_id' => $campaign1->id]);
    $link2 = TrackingLink::factory()->create(['campaign_id' => $campaign2->id]);

    TrackingClick::factory()->count(7)->create(['tracking_link_id' => $link1->id]);
    TrackingClick::factory()->count(3)->create(['tracking_link_id' => $link2->id]);

    Conversion::factory()->forCampaign($campaign1)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '15.00',
    ]);
    Conversion::factory()->forCampaign($campaign2)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '15.00',
    ]);

    CampaignExpense::factory()->forCampaign($campaign1)->create(['amount' => '10.00']);
    CampaignExpense::factory()->forCampaign($campaign2)->create(['amount' => '20.00']);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.click_count', 10)
        ->assertJsonPath('data.statistics.conversion_count', 2)
        ->assertJsonPath('data.statistics.revenue', '30.00')
        ->assertJsonPath('data.statistics.total_expenses', '30.00')
        ->assertJsonPath('data.statistics.profit', '0.00');
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

it('returns 401 for guest request', function (): void {
    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| API Response Shape
|--------------------------------------------------------------------------
*/

it('returns correct envelope structure', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'statistics' => [
                    'offer_count',
                    'campaign_count',
                    'active_campaign_count',
                    'click_count',
                    'conversion_count',
                    'revenue',
                    'total_expenses',
                    'profit',
                ],
            ],
        ]);
});

it('returns integer counts as integers, not strings', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    $response = getJson(route('api.v1.dashboard.statistics'));
    $data = $response->json('data.statistics');

    expect($data['offer_count'])->toBeInt()
        ->and($data['campaign_count'])->toBeInt()
        ->and($data['active_campaign_count'])->toBeInt()
        ->and($data['click_count'])->toBeInt()
        ->and($data['conversion_count'])->toBeInt();
});

it('returns financial values as exactly 2 decimal strings', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = getJson(route('api.v1.dashboard.statistics'));
    $data = $response->json('data.statistics');

    expect($data['revenue'])->toMatch('/^\d+\.\d{2}$/')
        ->and($data['total_expenses'])->toMatch('/^\d+\.\d{2}$/')
        ->and($data['profit'])->toMatch('/^[-]?\d+\.\d{2}$/');
});

/*
|--------------------------------------------------------------------------
| Decimal Precision
|--------------------------------------------------------------------------
*/

it('sums decimal values without floating-point errors', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '0.10']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '0.10',
    ]);
    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '0.20',
    ]);

    $response = getJson(route('api.v1.dashboard.statistics'));

    $response->assertOk()
        ->assertJsonPath('data.statistics.revenue', '0.30');
});

/*
|--------------------------------------------------------------------------
| Query Bound
|--------------------------------------------------------------------------
*/

it('performs a bounded number of queries', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);
    $link = TrackingLink::factory()->create(['campaign_id' => $campaign->id]);
    TrackingClick::factory()->count(5)->create(['tracking_link_id' => $link->id]);
    Conversion::factory()->forCampaign($campaign)->create(['status' => ConversionStatus::Approved, 'revenue' => '25.00']);
    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '10.00']);

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    getJson(route('api.v1.dashboard.statistics'));

    expect($queryCount)->toBeLessThanOrEqual(10);
});

/*
|--------------------------------------------------------------------------
| Blade Dashboard Integration
|--------------------------------------------------------------------------
*/

it('blade dashboard renders with statistics data', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '50.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Approved,
        'revenue' => '50.00',
    ]);
    CampaignExpense::factory()->forCampaign($campaign)->create(['amount' => '20.00']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('50.00')
        ->assertSee('20.00')
        ->assertSee('30.00');
});

it('blade dashboard revenue respects approved-only rule', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()->create(['user_id' => $user->id, 'payout' => '100.00']);
    $campaign = Campaign::factory()->create(['offer_id' => $offer->id, 'status' => CampaignStatus::Active]);

    Conversion::factory()->forCampaign($campaign)->create([
        'status' => ConversionStatus::Pending,
        'revenue' => '100.00',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('0.00');
});

it('blade dashboard does not confuse budget with expenses', function (): void {
    $user = User::factory()->create();

    $offer = Offer::factory()->create(['user_id' => $user->id]);
    Campaign::factory()->create(['offer_id' => $offer->id, 'budget' => '5000.00']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Expenses')
        ->assertSee('$0.00');
});
