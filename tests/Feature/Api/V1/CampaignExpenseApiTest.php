<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignExpense;
use App\Models\Offer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Expense Schema
|--------------------------------------------------------------------------
*/

it('creates the campaign_expenses table with the correct columns', function (): void {
    $columns = ['id', 'campaign_id', 'amount', 'spent_at', 'description', 'created_at', 'updated_at'];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('campaign_expenses', $column))->toBeTrue(
            "Missing column: {$column}"
        );
    }
});

it('does not have user_id, offer_id, category, type, source, reference, status, deleted_at columns', function (): void {
    $absent = ['user_id', 'offer_id', 'category', 'type', 'source', 'reference', 'status', 'deleted_at'];

    foreach ($absent as $column) {
        expect(Schema::hasColumn('campaign_expenses', $column))->toBeFalse(
            "Unexpected column: {$column}"
        );
    }
});

/*
|--------------------------------------------------------------------------
| CampaignExpense Model
|--------------------------------------------------------------------------
*/

it('has correct fillable fields', function (): void {
    $model = new CampaignExpense;

    expect($model->getFillable())->toBe([
        'campaign_id',
        'amount',
        'spent_at',
        'description',
    ]);
});

it('casts amount as decimal and spent_at as date', function (): void {
    $model = new CampaignExpense;

    $casts = $model->getCasts();

    expect($casts['amount'])->toBe('decimal:2')
        ->and($casts['spent_at'])->toBe('date');
});

it('has a campaign relationship', function (): void {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create();

    expect($expense->campaign)->not->toBeNull()
        ->and($expense->campaign_id)->toBe($expense->campaign->id);
});

/*
|--------------------------------------------------------------------------
| Campaign Expenses Relationship
|--------------------------------------------------------------------------
*/

it('has an expenses relationship on Campaign', function (): void {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create();

    expect($campaign->expenses)->not->toBeNull()
        ->and($campaign->expenses->first()->id)->toBe($expense->id);
});

/*
|--------------------------------------------------------------------------
| CampaignExpense Factory
|--------------------------------------------------------------------------
*/

it('creates a campaign expense from factory', function (): void {
    $expense = CampaignExpense::factory()->create();

    expect($expense)->toBeInstanceOf(CampaignExpense::class)
        ->and($expense->amount)->not->toBeNull()
        ->and($expense->spent_at)->not->toBeNull()
        ->and((float) $expense->amount)->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Create Expense - Authorization
|--------------------------------------------------------------------------
*/

it('allows the owner to record an expense for their campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    );

    $response->assertCreated();
});

it('rejects guests', function (): void {
    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::Active,
    ]);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertUnauthorized();

    assertDatabaseCount('campaign_expenses', 0);
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
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertForbidden();

    assertDatabaseCount('campaign_expenses', 0);
});

it('returns not found when the campaign does not exist', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    postJson(
        '/api/v1/campaigns/999999/expenses',
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertNotFound();

    assertDatabaseCount('campaign_expenses', 0);
});

/*
|--------------------------------------------------------------------------
| Create Expense - Campaign Status Independence
|--------------------------------------------------------------------------
*/

it('allows expense for a draft campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Draft,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertCreated();
});

it('allows expense for an active campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertCreated();
});

it('allows expense for a suspended campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Suspended,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertCreated();
});

/*
|--------------------------------------------------------------------------
| Create Expense - Validation
|--------------------------------------------------------------------------
*/

it('returns 422 when amount is missing', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    assertDatabaseCount('campaign_expenses', 0);
});

it('returns 422 when amount is zero', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '0',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    assertDatabaseCount('campaign_expenses', 0);
});

it('returns 422 when amount is negative', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '-50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    assertDatabaseCount('campaign_expenses', 0);
});

it('returns 422 when amount has more than 2 decimal places', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '12.345',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    assertDatabaseCount('campaign_expenses', 0);
});

it('returns 422 when amount exceeds DECIMAL(12,2) range', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '10000000000.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    assertDatabaseCount('campaign_expenses', 0);
});

it('accepts valid amount of 10', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '10',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertCreated();

    assertDatabaseHas('campaign_expenses', [
        'campaign_id' => $campaign->id,
        'amount' => '10.00',
    ]);
});

it('accepts valid amount of 10.5', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '10.5',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertCreated();

    assertDatabaseHas('campaign_expenses', [
        'campaign_id' => $campaign->id,
        'amount' => '10.50',
    ]);
});

it('accepts valid amount of 10.50', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '10.50',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertCreated();

    assertDatabaseHas('campaign_expenses', [
        'campaign_id' => $campaign->id,
        'amount' => '10.50',
    ]);
});

it('returns 422 when spent_at is missing', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('spent_at');

    assertDatabaseCount('campaign_expenses', 0);
});

it('returns 422 when spent_at is a future date', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->addDay()->toDateString(),
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('spent_at');

    assertDatabaseCount('campaign_expenses', 0);
});

it('accepts historical spent_at date', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $pastDate = now()->subDays(30)->toDateString();

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => $pastDate,
        ]
    )->assertCreated();

    assertDatabaseHas('campaign_expenses', [
        'campaign_id' => $campaign->id,
        'spent_at' => Carbon::parse($pastDate),
    ]);
});

it('accepts today as spent_at', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->toDateString(),
        ]
    )->assertCreated();
});

it('stores description when provided', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
            'description' => 'Facebook ad spend',
        ]
    )->assertCreated();

    assertDatabaseHas('campaign_expenses', [
        'campaign_id' => $campaign->id,
        'description' => 'Facebook ad spend',
    ]);
});

it('sets description to null when not provided', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertCreated();

    assertDatabaseHas('campaign_expenses', [
        'campaign_id' => $campaign->id,
        'description' => null,
    ]);
});

it('rejects campaign_id from request body', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $otherOffer = Offer::factory()->for($user)->create();
    $otherCampaign = Campaign::factory()->for($otherOffer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
            'campaign_id' => $otherCampaign->id,
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('campaign_id');

    assertDatabaseCount('campaign_expenses', 0);
});

it('records expense with correct attributes', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $spentAt = now()->subDay()->toDateString();

    $response = postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '125.50',
            'spent_at' => $spentAt,
            'description' => 'Google Ads campaign',
        ]
    );

    $response->assertCreated();

    assertDatabaseHas('campaign_expenses', [
        'campaign_id' => $campaign->id,
        'amount' => '125.50',
        'spent_at' => Carbon::parse($spentAt),
        'description' => 'Google Ads campaign',
    ]);
});

it('expense can exceed campaign budget', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
        'budget' => '100.00',
    ]);

    Sanctum::actingAs($user);

    postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '150.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    )->assertCreated();

    assertDatabaseHas('campaign_expenses', [
        'campaign_id' => $campaign->id,
        'amount' => '150.00',
    ]);
});

/*
|--------------------------------------------------------------------------
| Create Expense - Response Shape
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
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '75.25',
            'spent_at' => now()->subDay()->toDateString(),
            'description' => 'TikTok ads',
        ]
    );

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'campaign_expense' => [
                    'id',
                    'campaign_id',
                    'amount',
                    'spent_at',
                    'description',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

    $expense = $response->json('data.campaign_expense');

    expect($expense['campaign_id'])->toBe($campaign->id)
        ->and($expense['amount'])->toBe('75.25')
        ->and($expense['description'])->toBe('TikTok ads');
});

it('does not expose user_id, offer_id, or internal fields', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $response = postJson(
        route('api.v1.campaigns.expenses.store', $campaign),
        [
            'amount' => '50.00',
            'spent_at' => now()->subDay()->toDateString(),
        ]
    );

    $data = $response->json('data.campaign_expense');

    expect($data)->not->toHaveKey('user_id')
        ->and($data)->not->toHaveKey('offer_id')
        ->and($data)->not->toHaveKey('budget')
        ->and($data)->not->toHaveKey('profit')
        ->and($data)->not->toHaveKey('roi');
});

/*
|--------------------------------------------------------------------------
| Index Expenses
|--------------------------------------------------------------------------
*/

it('allows the owner to list expenses for their campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    CampaignExpense::factory()->forCampaign($campaign)->count(3)->create();

    Sanctum::actingAs($user);

    getJson(
        route('api.v1.campaigns.expenses.index', $campaign)
    )->assertOk();
});

it('excludes expenses from other campaigns', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $otherOffer = Offer::factory()->for($user)->create();
    $otherCampaign = Campaign::factory()->for($otherOffer)->create([
        'status' => CampaignStatus::Active,
    ]);

    CampaignExpense::factory()->forCampaign($campaign)->create(['description' => 'My expense']);
    CampaignExpense::factory()->forCampaign($otherCampaign)->create(['description' => 'Other expense']);

    Sanctum::actingAs($user);

    $response = getJson(
        route('api.v1.campaigns.expenses.index', $campaign)
    );

    $response->assertOk();

    $data = $response->json('data');
    $descriptions = array_column($data, 'description');

    expect($descriptions)->toContain('My expense')
        ->and($descriptions)->not->toContain('Other expense');
});

it('paginates with 15 records per page', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    CampaignExpense::factory()->forCampaign($campaign)->count(20)->create();

    Sanctum::actingAs($user);

    $response = getJson(
        route('api.v1.campaigns.expenses.index', $campaign)
    );

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(15);

    $meta = $response->json('meta');
    expect($meta['total'])->toBe(20)
        ->and($meta['per_page'])->toBe(15);
});

it('orders by spent_at DESC then id DESC', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $date1 = now()->subDays(3)->toDateString();
    $date2 = now()->subDays(1)->toDateString();
    $date3 = now()->subDays(2)->toDateString();

    CampaignExpense::factory()->forCampaign($campaign)->create(['spent_at' => $date1, 'id' => 1]);
    CampaignExpense::factory()->forCampaign($campaign)->create(['spent_at' => $date2, 'id' => 2]);
    CampaignExpense::factory()->forCampaign($campaign)->create(['spent_at' => $date3, 'id' => 3]);

    Sanctum::actingAs($user);

    $response = getJson(
        route('api.v1.campaigns.expenses.index', $campaign)
    );

    $data = $response->json('data');

    expect($data[0]['spent_at'])->toBe($date2)
        ->and($data[1]['spent_at'])->toBe($date3)
        ->and($data[2]['spent_at'])->toBe($date1);
});

it('rejects foreign campaign list', function (): void {
    $owner = User::factory()->create();
    $foreignUser = User::factory()->create();

    $offer = Offer::factory()->for($owner)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    Sanctum::actingAs($foreignUser);

    getJson(
        route('api.v1.campaigns.expenses.index', $campaign)
    )->assertForbidden();
});

it('returns 404 for unknown campaign list', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    getJson(
        '/api/v1/campaigns/999999/expenses'
    )->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Update Expense
|--------------------------------------------------------------------------
*/

it('allows owner to update expense amount', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create([
        'amount' => '50.00',
    ]);

    Sanctum::actingAs($user);

    patchJson(
        route('api.v1.campaigns.expenses.update', [$campaign, $expense]),
        [
            'amount' => '75.00',
        ]
    )->assertOk();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expense->id,
        'amount' => '75.00',
    ]);
});

it('allows owner to update expense spent_at', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create([
        'spent_at' => now()->subDays(5)->toDateString(),
    ]);

    Sanctum::actingAs($user);

    $newDate = now()->subDays(10)->toDateString();

    patchJson(
        route('api.v1.campaigns.expenses.update', [$campaign, $expense]),
        [
            'spent_at' => $newDate,
        ]
    )->assertOk();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expense->id,
        'spent_at' => Carbon::parse($newDate),
    ]);
});

it('allows owner to update expense description', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create([
        'description' => 'Old description',
    ]);

    Sanctum::actingAs($user);

    patchJson(
        route('api.v1.campaigns.expenses.update', [$campaign, $expense]),
        [
            'description' => 'New description',
        ]
    )->assertOk();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expense->id,
        'description' => 'New description',
    ]);
});

it('returns 422 on update with invalid amount', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create([
        'amount' => '50.00',
    ]);

    Sanctum::actingAs($user);

    patchJson(
        route('api.v1.campaigns.expenses.update', [$campaign, $expense]),
        [
            'amount' => '-10.00',
        ]
    )->assertUnprocessable();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expense->id,
        'amount' => '50.00',
    ]);
});

/*
|--------------------------------------------------------------------------
| Nested-Resource Security
|--------------------------------------------------------------------------
*/

it('returns 404 when expense belongs to another owned campaign', function (): void {
    $user = User::factory()->create();

    $offerA = Offer::factory()->for($user)->create();
    $campaignA = Campaign::factory()->for($offerA)->create([
        'status' => CampaignStatus::Active,
    ]);

    $offerB = Offer::factory()->for($user)->create();
    $campaignB = Campaign::factory()->for($offerB)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expenseB = CampaignExpense::factory()->forCampaign($campaignB)->create();

    Sanctum::actingAs($user);

    patchJson(
        route('api.v1.campaigns.expenses.update', [$campaignA, $expenseB]),
        [
            'amount' => '999.00',
        ]
    )->assertNotFound();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expenseB->id,
        'amount' => $expenseB->amount,
    ]);
});

it('returns 403 when expense belongs to foreign user campaign', function (): void {
    $owner = User::factory()->create();
    $foreignUser = User::factory()->create();

    $offer = Offer::factory()->for($owner)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($foreignUser);

    patchJson(
        route('api.v1.campaigns.expenses.update', [$campaign, $expense]),
        [
            'amount' => '999.00',
        ]
    )->assertForbidden();
});

it('does not modify foreign expense on update', function (): void {
    $user = User::factory()->create();

    $offerA = Offer::factory()->for($user)->create();
    $campaignA = Campaign::factory()->for($offerA)->create([
        'status' => CampaignStatus::Active,
    ]);

    $offerB = Offer::factory()->for($user)->create();
    $campaignB = Campaign::factory()->for($offerB)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expenseB = CampaignExpense::factory()->forCampaign($campaignB)->create([
        'amount' => '100.00',
    ]);

    Sanctum::actingAs($user);

    patchJson(
        route('api.v1.campaigns.expenses.update', [$campaignA, $expenseB]),
        [
            'amount' => '999.00',
        ]
    )->assertNotFound();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expenseB->id,
        'amount' => '100.00',
    ]);
});

/*
|--------------------------------------------------------------------------
| Delete Expense
|--------------------------------------------------------------------------
*/

it('allows owner to delete expense', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($user);

    deleteJson(
        route('api.v1.campaigns.expenses.destroy', [$campaign, $expense])
    )->assertNoContent();

    assertDatabaseMissing('campaign_expenses', [
        'id' => $expense->id,
    ]);
});

it('returns 404 when deleting expense from wrong parent campaign', function (): void {
    $user = User::factory()->create();

    $offerA = Offer::factory()->for($user)->create();
    $campaignA = Campaign::factory()->for($offerA)->create([
        'status' => CampaignStatus::Active,
    ]);

    $offerB = Offer::factory()->for($user)->create();
    $campaignB = Campaign::factory()->for($offerB)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expenseB = CampaignExpense::factory()->forCampaign($campaignB)->create();

    Sanctum::actingAs($user);

    deleteJson(
        route('api.v1.campaigns.expenses.destroy', [$campaignA, $expenseB])
    )->assertNotFound();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expenseB->id,
    ]);
});

it('returns 403 when deleting foreign user expense', function (): void {
    $owner = User::factory()->create();
    $foreignUser = User::factory()->create();

    $offer = Offer::factory()->for($owner)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create();

    Sanctum::actingAs($foreignUser);

    deleteJson(
        route('api.v1.campaigns.expenses.destroy', [$campaign, $expense])
    )->assertForbidden();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expense->id,
    ]);
});

it('does not delete foreign expense', function (): void {
    $user = User::factory()->create();

    $offerA = Offer::factory()->for($user)->create();
    $campaignA = Campaign::factory()->for($offerA)->create([
        'status' => CampaignStatus::Active,
    ]);

    $offerB = Offer::factory()->for($user)->create();
    $campaignB = Campaign::factory()->for($offerB)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expenseB = CampaignExpense::factory()->forCampaign($campaignB)->create();

    Sanctum::actingAs($user);

    deleteJson(
        route('api.v1.campaigns.expenses.destroy', [$campaignA, $expenseB])
    )->assertNotFound();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expenseB->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Cascade Behavior
|--------------------------------------------------------------------------
*/

it('deletes expenses when campaign is deleted', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    CampaignExpense::factory()->forCampaign($campaign)->count(3)->create();

    assertDatabaseCount('campaign_expenses', 3);

    $campaign->delete();

    assertDatabaseCount('campaign_expenses', 0);
});

/*
|--------------------------------------------------------------------------
| Security / Integrity
|--------------------------------------------------------------------------
*/

it('preserves offer ownership derivation through campaign', function (): void {
    $user = User::factory()->create();
    $offer = Offer::factory()->for($user)->create();
    $campaign = Campaign::factory()->for($offer)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expense = CampaignExpense::factory()->forCampaign($campaign)->create();

    expect($expense->campaign->offer->user_id)->toBe($user->id);
});

it('nested child resolution cannot cross campaign boundary', function (): void {
    $user = User::factory()->create();

    $offerA = Offer::factory()->for($user)->create();
    $campaignA = Campaign::factory()->for($offerA)->create([
        'status' => CampaignStatus::Active,
    ]);

    $offerB = Offer::factory()->for($user)->create();
    $campaignB = Campaign::factory()->for($offerB)->create([
        'status' => CampaignStatus::Active,
    ]);

    $expenseB = CampaignExpense::factory()->forCampaign($campaignB)->create();

    Sanctum::actingAs($user);

    $response = deleteJson(
        route('api.v1.campaigns.expenses.destroy', [$campaignA, $expenseB])
    );

    $response->assertNotFound();

    assertDatabaseHas('campaign_expenses', [
        'id' => $expenseB->id,
    ]);
});
