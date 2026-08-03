# Design - KAN-18: Afficher les statistiques du dashboard

## 1. Existing Conventions Inspected

The design follows these implemented conventions:

- `routes/api.php`: `/api/v1` prefix, `auth:sanctum` grouping, named routes.
- `DashboardController`: Thin controller, renders Blade view with ownership-scoped data.
- `RecordConversionAction`: Action pattern with `execute()` method.
- `RecordCampaignExpenseAction`: Action pattern with `execute()` method.
- `ConversionResource`: JsonResource with `number_format()` for DECIMAL serialization.
- `CampaignExpenseResource`: JsonResource with `number_format()` for DECIMAL serialization.
- `CampaignPolicy`: Ownership derived through `Campaign → Offer → User`.
- Pest feature tests: `RefreshDatabase`, `Sanctum::actingAs()`, database assertions.
- API response envelope: `data.model_name`.
- Validation conventions: `decimal:0,2`, `max:9999999999.99` for DECIMAL(12,2) fields.
- `composer.json`: Laravel 13.8, PHP ^8.3, Pest ^4.7.

## 2. Architecture

### 2.1 Component responsibilities

| Component | Responsibility |
|---|---|
| `DashboardStatisticsController` | Resolve authenticated user, call Action, return Resource response |
| `GetDashboardStatisticsAction` | Execute all aggregate queries scoped to the user, return statistics array |
| `DashboardStatisticsResource` | Serialize statistics for JSON response |
| `DashboardController` | Extend existing Blade controller to pass statistics to view |
| `DashboardStatisticsTest` | Pest tests covering all metrics, authorization, edge cases |

### 2.2 Flow

```
API: GET /api/v1/dashboard/statistics
  → auth:sanctum middleware
  → DashboardStatisticsController::show()
  → GetDashboardStatisticsAction::execute($user)
  → aggregate database queries (SUM, COUNT)
  → DashboardStatisticsResource
  → JSON response

Blade: GET /dashboard
  → DashboardController::index()
  → GetDashboardStatisticsAction::execute($user)
  → pass statistics to Blade view
  → view renders real data
```

### 2.3 Why a separate Action

The statistics Action is shared between the API endpoint and the Blade dashboard. This avoids duplicating aggregation logic across two controllers. The Action accepts a `User` model and returns a plain array — it has no HTTP dependency.

## 3. Statistics Metrics

### 3.1 Metric definitions

| # | Metric | Formula | Data Source | Decimal Precision |
|---|--------|---------|-------------|-------------------|
| 1 | `offer_count` | `COUNT(offers)` | `offers.user_id = user.id` | Integer |
| 2 | `campaign_count` | `COUNT(campaigns)` | `campaigns.offer.user_id = user.id` | Integer |
| 3 | `active_campaign_count` | `COUNT(campaigns WHERE status = active)` | `campaigns.offer.user_id = user.id AND campaigns.status = 'active'` | Integer |
| 4 | `click_count` | `COUNT(tracking_clicks)` | `tracking_clicks.tracking_link.campaign.offer.user_id = user.id` | Integer |
| 5 | `conversion_count` | `COUNT(conversions)` | `conversions.campaign.offer.user_id = user.id` + status filter | Integer |
| 6 | `revenue` | `SUM(conversions.revenue)` | `conversions.campaign.offer.user_id = user.id` + status filter | DECIMAL(12,2) |
| 7 | `total_expenses` | `SUM(campaign_expenses.amount)` | `campaign_expenses.campaign.offer.user_id = user.id` | DECIMAL(12,2) |
| 8 | `profit` | `revenue − total_expenses` | Computed from metrics 6 and 7 | DECIMAL(12,2) |

### 3.2 Conversion status semantics

**Resolved by product decision:**

- `conversion_count`: counts ALL Conversion records (pending, approved, rejected)
- `revenue`: SUM of `Conversion.revenue` WHERE `status = ConversionStatus::Approved` only
- `profit`: approved revenue − total expenses

The Action queries conversions and approved revenue separately. No configurable status filter parameter is used — the semantics are fixed per metric.

### 3.3 Zero-data behavior

| Metric | Zero-data value |
|--------|----------------|
| `offer_count` | `0` |
| `campaign_count` | `0` |
| `active_campaign_count` | `0` |
| `click_count` | `0` |
| `conversion_count` | `0` |
| `revenue` | `"0.00"` |
| `total_expenses` | `"0.00"` |
| `profit` | `"0.00"` |

All metrics use `0` or `"0.00"` — never `null`. This ensures the frontend can always render numeric values without null-checking.

### 3.4 Negative profit

When `total_expenses > revenue`, profit is negative. For example:
- Revenue: `"500.00"`
- Total expenses: `"750.00"`
- Profit: `"-250.00"`

The API serializes negative profit as `"-250.00"` — standard decimal format with sign.

## 4. Query Strategy

All queries are scoped to the authenticated user at the database level through the ownership chain: `User → Offer → Campaign → child records`. No post-query PHP filtering.

### 4.1 Offer count

```php
Offer::where('user_id', $user->id)->count();
```

Single query. Uses existing `user_id` index on `offers`.

### 4.2 Campaign count

```php
Campaign::whereHas('offer', fn ($q) => $q->where('user_id', $user->id))->count();
```

Single query with subquery. Uses existing `user_id` index on `offers`.

### 4.3 Active campaign count

```php
Campaign::whereHas('offer', fn ($q) => $q->where('user_id', $user->id))
    ->where('status', CampaignStatus::Active)
    ->count();
```

Single query with subquery and status filter. Uses existing indexes.

### 4.4 Click count

```php
TrackingClick::whereHas('trackingLink.campaign.offer', fn ($q) => $q->where('user_id', $user->id))->count();
```

Single query with nested subquery through: `tracking_clicks → tracking_links → campaigns → offers → users`.

### 4.5 Conversion count and revenue

```php
Conversion::whereHas('campaign.offer', fn ($q) => $q->where('user_id', $user->id))
    ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
    ->selectRaw('COUNT(*) as count, COALESCE(SUM(revenue), 0) as total')
    ->first();
```

Single query combining count and sum. Uses existing `status` index on `conversions`. `COALESCE` returns `0` when no rows match.

### 4.6 Total expenses

```php
CampaignExpense::whereHas('campaign.offer', fn ($q) => $q->where('user_id', $user->id))
    ->selectRaw('COALESCE(SUM(amount), 0) as total')
    ->value('total');
```

Single query with `COALESCE` for zero-data safety.

### 4.7 Total query count

| Metric | Queries |
|--------|---------|
| offer_count | 1 |
| campaign_count | 1 |
| active_campaign_count | 1 |
| click_count | 1 |
| conversion_count + revenue | 1 |
| total_expenses | 1 |
| **Total** | **6 queries** |

Six indexed queries. No N+1. No collection-based aggregation. All aggregates are database-side.

### 4.8 Index adequacy

| Table | Existing index | Used for |
|-------|---------------|----------|
| `offers` | `user_id` (FK) | offer_count, ownership scoping |
| `campaigns` | `offer_id` (FK) | campaign_count, ownership chain |
| `conversions` | `campaign_id` (FK), `status` (index) | conversion_count, revenue, status filter |
| `campaign_expenses` | `campaign_id` (FK) | total_expenses |
| `tracking_clicks` | `tracking_link_id` (FK) | click_count |
| `tracking_links` | `campaign_id` (FK) | click_count chain |

No new migration or index is required. Existing foreign key indexes are sufficient for the aggregation queries.

## 5. API Endpoint Design

### 5.1 Route

| Property | Value |
|----------|-------|
| Method | `GET` |
| URI | `/api/v1/dashboard/statistics` |
| Route name | `api.v1.dashboard.statistics` |
| Authentication | `auth:sanctum` |
| Controller | `DashboardStatisticsController::show` |
| Authorization | Authenticated user only — no resource-level policy needed |

### 5.2 Why no Policy

The statistics endpoint returns data scoped to the authenticated user. There is no specific resource being accessed — the user always sees their own data. The `auth:sanctum` middleware is sufficient. No `CampaignPolicy` check is needed because the Action queries by `user_id` directly.

### 5.3 Response structure

```json
{
    "data": {
        "statistics": {
            "offer_count": 12,
            "campaign_count": 8,
            "active_campaign_count": 5,
            "click_count": 1547,
            "conversion_count": 42,
            "revenue": "525.00",
            "total_expenses": "310.50",
            "profit": "214.50"
        }
    }
}
```

### 5.4 Empty-user response

```json
{
    "data": {
        "statistics": {
            "offer_count": 0,
            "campaign_count": 0,
            "active_campaign_count": 0,
            "click_count": 0,
            "conversion_count": 0,
            "revenue": "0.00",
            "total_expenses": "0.00",
            "profit": "0.00"
        }
    }
}
```

### 5.5 Decimal serialization

Financial values (`revenue`, `total_expenses`, `profit`) are serialized as two-decimal strings: `"1250.50"`, `"-250.00"`, `"0.00"`. This matches the existing convention in `ConversionResource` and `CampaignExpenseResource` which use `number_format((float) $value, 2, '.', '')`.

Integer counts (`offer_count`, `campaign_count`, etc.) are serialized as JSON integers.

## 6. Resource Design

```php
class DashboardStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'offer_count' => $this->resource['offer_count'],
            'campaign_count' => $this->resource['campaign_count'],
            'active_campaign_count' => $this->resource['active_campaign_count'],
            'click_count' => $this->resource['click_count'],
            'conversion_count' => $this->resource['conversion_count'],
            'revenue' => number_format((float) $this->resource['revenue'], 2, '.', ''),
            'total_expenses' => number_format((float) $this->resource['total_expenses'], 2, '.', ''),
            'profit' => number_format((float) $this->resource['profit'], 2, '.', ''),
        ];
    }
}
```

## 7. Action Design

```php
class GetDashboardStatisticsAction
{
    public function execute(
        User $user,
        ?array $conversionStatuses = null,
    ): array {
        $offerCount = Offer::where('user_id', $user->id)->count();

        $campaignCount = Campaign::whereHas('offer', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        $activeCampaignCount = Campaign::whereHas('offer', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', CampaignStatus::Active)
            ->count();

        $clickCount = TrackingClick::whereHas(
            'trackingLink.campaign.offer',
            fn ($q) => $q->where('user_id', $user->id)
        )->count();

        $conversionQuery = Conversion::whereHas(
            'campaign.offer',
            fn ($q) => $q->where('user_id', $user->id)
        );

        if ($conversionStatuses !== null) {
            $conversionQuery->whereIn('status', $conversionStatuses);
        }

        $conversionStats = $conversionQuery
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(revenue), 0) as total')
            ->first();

        $totalExpenses = CampaignExpense::whereHas(
            'campaign.offer',
            fn ($q) => $q->where('user_id', $user->id)
        )->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $revenue = (float) $conversionStats->total;
        $expenses = (float) $totalExpenses;

        return [
            'offer_count' => $offerCount,
            'campaign_count' => $campaignCount,
            'active_campaign_count' => $activeCampaignCount,
            'click_count' => $clickCount,
            'conversion_count' => (int) $conversionStats->count,
            'revenue' => $revenue,
            'total_expenses' => $expenses,
            'profit' => $revenue - $expenses,
        ];
    }
}
```

### 7.1 Why `?array $conversionStatuses = null`

The parameter allows the caller to filter conversions by status. When `null`, all conversions are included. This design enables:
- Current behavior: count all conversions (pending decision).
- Future behavior: filter by `[ConversionStatus::Approved]` when approval workflow exists.
- KAN-19: add period filtering by passing additional query constraints.

### 7.2 Blade dashboard integration

The existing `DashboardController::index()` will be extended to call `GetDashboardStatisticsAction` and pass the result to the Blade view. No new route is needed — the existing `GET /dashboard` route is reused.

## 8. Controller Design

```php
class DashboardStatisticsController extends Controller
{
    public function show(
        Request $request,
        GetDashboardStatisticsAction $action,
    ): JsonResponse {
        $statistics = $action->execute($request->user());

        return response()->json([
            'data' => [
                'statistics' => new DashboardStatisticsResource($statistics),
            ],
        ]);
    }
}
```

Thin controller. No business logic. Action handles all aggregation.

## 9. Blade Integration

### 9.1 Current state

The existing `dashboard.blade.php` shows:
- Offer count
- Campaign count
- Recent offers table
- Recent campaigns table
- Quick action buttons

### 9.2 KAN-18 changes

Add a statistics summary section above the existing content showing:
- Click count
- Conversion count
- Revenue
- Total expenses
- Profit

The existing offer/campaign counts will be sourced from the statistics Action instead of separate queries.

### 9.3 Scope

Minimal integration — add a statistics card/section to the existing Blade view. No redesign. No new visualization packages.

## 10. Security

### 10.1 Ownership isolation

All queries scope to `$user->id` through the ownership chain at the database level. No post-query filtering.

### 10.2 Authentication required

`auth:sanctum` for API, `auth` + `verified` middleware for Blade. Guest requests return `401` (API) or `302` redirect to login (Blade).

### 10.3 No client-controlled parameters

The API endpoint accepts no query parameters. Statistics are always computed for the full all-time scope of the authenticated user. Period filtering is deferred to KAN-19.

## 11. Postman/Newman Collection

### 11.1 Planned flow

| # | Step | Method | Expected |
|---|------|--------|----------|
| 1 | Health check | GET `/api/v1/health` | 200 |
| 2 | Register owner | POST `/api/v1/auth/register` | 201 |
| 3 | Login owner | POST `/api/v1/auth/login` | 200 |
| 4 | Request statistics (empty) | GET `/api/v1/dashboard/statistics` | 200 — all zeros |
| 5 | Create offer | POST `/api/v1/offers` | 201 |
| 6 | Create campaign | POST `/api/v1/campaigns` | 201 |
| 7 | Request statistics (with data) | GET `/api/v1/dashboard/statistics` | 200 — counts updated |
| 8 | Guest request | GET `/api/v1/dashboard/statistics` | 401 |

### 11.2 Limitations

- Conversions can only be created as `pending` status through the public API. If the status filter requires `approved`, the Postman collection cannot demonstrate revenue through public endpoints alone.
- Pest tests will cover all database-state scenarios that cannot be prepared via HTTP.

## 12. Pest Test Plan

### 12.1 Statistics domain

| # | Test | Scenario |
|---|------|----------|
| 1 | New user gets zero statistics | All metrics = 0 |
| 2 | Offer count correct | Create 3 offers → count = 3 |
| 3 | Campaign count correct | Create 2 campaigns → count = 2 |
| 4 | Active campaign count correct | 1 active, 1 draft → active_count = 1 |
| 5 | Click count correct | Create tracking link + 5 clicks → count = 5 |
| 6 | Conversion count correct | Create 3 conversions → count = 3 |
| 7 | Revenue sums correctly | 2 conversions with revenue 10 + 20 → revenue = "30.00" |
| 8 | Total expenses sums correctly | 2 expenses 50 + 75 → total_expenses = "125.00" |
| 9 | Profit computed correctly | revenue 100 - expenses 60 → profit = "40.00" |
| 10 | Negative profit works | revenue 30 - expenses 100 → profit = "-70.00" |

### 12.2 Ownership isolation

| # | Test | Scenario |
|---|------|----------|
| 11 | Foreign campaigns excluded | User B's campaigns don't appear in User A's stats |
| 12 | Foreign conversions excluded | User B's conversions don't inflate User A's revenue |
| 13 | Foreign expenses excluded | User B's expenses don't appear in User A's totals |
| 14 | Foreign clicks excluded | User B's clicks don't appear in User A's count |
| 15 | Mixed datasets remain isolated | Both users have data — each sees only their own |

### 12.3 Authorization

| # | Test | Scenario |
|---|------|----------|
| 16 | Guest returns 401 | Unauthenticated request |
| 17 | Unknown campaign returns N/A | No campaign parameter needed |

### 12.4 Edge cases

| # | Test | Scenario |
|---|------|----------|
| 18 | Campaign budget not counted as expense | budget = 1000, expenses = 0 → total_expenses = "0.00" |
| 19 | Rejected conversions excluded (if filter active) | Depends on status decision |
| 20 | Decimal sums remain exact | 0.10 + 0.20 = "0.30", not "0.30000000000000004" |
| 21 | API response has correct envelope structure | `data.statistics` exists |
| 22 | Integer counts are integers, not strings | offer_count = 5, not "5" |

### 12.5 Regression

| # | Test | Scenario |
|---|------|----------|
| 23 | KAN-14 regression | TrackingLinkGenerationApiTest passes |
| 24 | KAN-15 regression | TrackingRedirectTest passes |
| 25 | KAN-16 regression | ConversionApiTest passes |
| 26 | KAN-17 regression | CampaignExpenseApiTest passes |
| 27 | Campaign regression | CampaignManagementApiTest passes |
| 28 | Full suite passes | `php artisan test` — baseline 402 + new tests |
