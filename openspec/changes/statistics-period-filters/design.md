# Design - KAN-19: Filtrer les statistiques par période

## 1. Existing Conventions Inspected

The design follows these implemented conventions:

- `routes/api.php`: `/api/v1` prefix, `auth:sanctum` grouping, named routes.
- `DashboardStatisticsController`: Thin controller, resolves authenticated user, calls Action, returns Resource response.
- `GetDashboardStatisticsAction`: Action pattern with `execute(User $user): array`.
- `DashboardStatisticsResource`: JsonResource with `number_format()` for DECIMAL serialization.
- `DashboardController`: Thin Blade controller, renders dashboard view with ownership-scoped data.
- `IndexOfferRequest`: Form Request with `authorize()`, `rules()`, `prepareForValidation()` for nullable normalization.
- `StoreCampaignExpenseRequest`: Form Request with `authorize()`, `rules()`, validates `spent_at` as `required|date|before_or_equal:today`.
- Pest feature tests: `RefreshDatabase`, `Sanctum::actingAs()`, database assertions.
- `composer.json`: Laravel 13.8, PHP ^8.3, Pest ^4.7.
- `config/app.php`: timezone `UTC`.

## 2. Architecture

### 2.1 Component responsibilities

| Component | Responsibility |
|---|---|
| `DashboardStatisticsRequest` | Validate optional `period`, `from`, `to` query parameters |
| `DashboardStatisticsPeriod` | Typed value object holding resolved `?CarbonImmutable $start` and `?CarbonImmutable $endExclusive` |
| `GetDashboardStatisticsAction` | Accept optional `DashboardStatisticsPeriod`, apply date constraints inside SQL |
| `DashboardStatisticsController` | Resolve authenticated user, pass validated params to Action |
| `DashboardController` | Accept query parameters, pass period to Action, pass to Blade view |
| `dashboard.blade.php` | Compact period selector UI using URL-backed query state |

### 2.2 Flow

```
API: GET /api/v1/dashboard/statistics?period=last_30_days
  → auth:sanctum middleware
  → DashboardStatisticsRequest validates query params
  → DashboardStatisticsController::show()
  → DashboardStatisticsPeriod::fromRequest($request)
  → GetDashboardStatisticsAction::execute($user, $period)
  → aggregate database queries with date conditions
  → DashboardStatisticsResource
  → JSON response

Blade: GET /dashboard?period=last_30_days
  → auth + verified middleware
  → DashboardController::index()
  → DashboardStatisticsRequest validates query params
  → DashboardStatisticsPeriod::fromRequest($request)
  → GetDashboardStatisticsAction::execute($user, $period)
  → pass statistics + period to Blade view
  → view renders filtered data
```

### 2.3 Why extend, not duplicate

KAN-18 designed the Action to accept future parameters. KAN-19 adds a `?DashboardStatisticsPeriod $period = null` parameter. The Action remains the single source of truth for statistics computation, shared between API and Blade.

## 3. Period Parameter Design

### 3.1 Supported periods

| Value | Meaning | Resolved boundaries |
|---|---|---|
| *(omitted)* | All-time (KAN-18 default) | `start = null`, `endExclusive = null` |
| `today` | Current day in UTC | `start = startOfToday()`, `endExclusive = startOfTomorrow()` |
| `last_7_days` | Today + 6 previous days | `start = startOfToday() - 6 days`, `endExclusive = startOfTomorrow()` |
| `last_30_days` | Today + 29 previous days | `start = startOfToday() - 29 days`, `endExclusive = startOfTomorrow()` |
| `this_month` | First day of current month to today | `start = startOfMonth()`, `endExclusive = startOfTomorrow()` |
| `custom` | User-defined range via `from` + `to` | `start = startOfDay(from)`, `endExclusive = startOfDay(to) + 1 day` |

### 3.2 Custom range parameters

When `period=custom`:
- `from` — required, date format `YYYY-MM-DD`, not in future
- `to` — required, date format `YYYY-MM-DD`, not in future
- `from` must be `<= to`
- Both dates are inclusive from the user's perspective (half-open internally)

### 3.3 Query parameter specification

| Parameter | Type | Required | Default | Allowed values |
|---|---|---|---|---|
| `period` | string | No | `null` (all-time) | `today`, `last_7_days`, `last_30_days`, `this_month`, `custom` |
| `from` | string | Only when `period=custom` | `null` | `YYYY-MM-DD`, not future |
| `to` | string | Only when `period=custom` | `null` | `YYYY-MM-DD`, not future |

### 3.4 Incompatible combinations

| Scenario | Behavior |
|---|---|
| `period` not provided, `from`/`to` present | 422 — from/to prohibited without custom |
| `period=custom` without `from` or `to` | 422 Validation error |
| `period=custom` with `from` only | 422 — to required |
| `period=custom` with `to` only | 422 — from required |
| Predefined period with `from`/`to` | 422 — from/to prohibited with predefined periods |
| `from` > `to` | 422 Validation error |
| Invalid `period` value | 422 Validation error |
| Invalid date format | 422 Validation error |
| Future `from` or `to` | 422 Validation error |

## 4. Value Object Design

### 4.1 DashboardStatisticsPeriod

```php
final readonly class DashboardStatisticsPeriod
{
    private function __construct(
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $endExclusive,
        public ?string $selectedPeriod,
    ) {}

    public static function allTime(): self
    {
        return new self(null, null, null);
    }

    public static function today(): self
    {
        $tz = config('app.timezone');
        $start = CarbonImmutable::now($tz)->startOfDay();
        $end = $start->addDay();

        return new self($start, $end, 'today');
    }

    public static function last7Days(): self
    {
        $tz = config('app.timezone');
        $start = CarbonImmutable::now($tz)->startOfDay()->subDays(6);
        $end = CarbonImmutable::now($tz)->startOfDay()->addDay();

        return new self($start, $end, 'last_7_days');
    }

    public static function last30Days(): self
    {
        $tz = config('app.timezone');
        $start = CarbonImmutable::now($tz)->startOfDay()->subDays(29);
        $end = CarbonImmutable::now($tz)->startOfDay()->addDay();

        return new self($start, $end, 'last_30_days');
    }

    public static function thisMonth(): self
    {
        $tz = config('app.timezone');
        $start = CarbonImmutable::now($tz)->startOfMonth();
        $end = CarbonImmutable::now($tz)->startOfDay()->addDay();

        return new self($start, $end, 'this_month');
    }

    public static function custom(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $start = $from->startOfDay();
        $end = $to->startOfDay()->addDay();

        return new self($start, $end, 'custom');
    }

    public static function fromRequest(Request $request): self
    {
        $period = $request->input('period');

        return match ($period) {
            null => self::allTime(),
            'today' => self::today(),
            'last_7_days' => self::last7Days(),
            'last_30_days' => self::last30Days(),
            'this_month' => self::thisMonth(),
            'custom' => self::custom(
                CarbonImmutable::parse($request->input('from'), config('app.timezone')),
                CarbonImmutable::parse($request->input('to'), config('app.timezone')),
            ),
            default => self::allTime(),
        };
    }

    public function isAllTime(): bool
    {
        return $this->start === null && $this->endExclusive === null;
    }
}
```

### 4.2 Why half-open boundaries

For TIMESTAMP columns (`created_at`, `converted_at`), using half-open `[start, end)` avoids fragile `23:59:59` logic:

```sql
WHERE created_at >= '2026-08-03 00:00:00'
  AND created_at < '2026-08-04 00:00:00'
```

This safely includes sub-second timestamps like `2026-08-03 23:59:59.999`.

For DATE columns (`spent_at`), the column stores a date without time. The half-open range still works:

```sql
WHERE spent_at >= '2026-08-03'
  AND spent_at < '2026-08-04'
```

## 5. Metric Date-Column Strategy

### 5.1 Date column table

| Metric | Period affected? | Date column | Column type | Reason |
|---|---|---|---|---|
| `offer_count` | No | N/A — all-time count | N/A | Inventory metric |
| `campaign_count` | No | N/A — all-time count | N/A | Inventory metric |
| `active_campaign_count` | No | N/A — all-time count | N/A | Status metric, no history |
| `click_count` | Yes | `tracking_clicks.created_at` | TIMESTAMP | Authoritative click timestamp |
| `conversion_count` | Yes | `conversions.converted_at` | TIMESTAMP | Business event timestamp |
| `revenue` | Yes | `conversions.converted_at` + approved | TIMESTAMP | Revenue follows conversion date |
| `total_expenses` | Yes | `campaign_expenses.spent_at` | DATE | Client-controlled expense date |
| `profit` | Yes | (computed) | N/A | Period revenue − period expenses |

## 6. Query Strategy

### 6.1 Action method signature

```php
public function execute(
    User $user,
    ?DashboardStatisticsPeriod $period = null,
): array
```

### 6.2 Date-conditioned queries

When `$period` is not null and not all-time, apply `where` conditions inside SQL:

```php
$hasPeriod = $period !== null && !$period->isAllTime();

$clickCount = TrackingClick::whereHas(
    'trackingLink.campaign.offer',
    fn ($q) => $q->where('user_id', $user->id)
)->when($hasPeriod, function ($q) use ($period) {
    $q->where('created_at', '>=', $period->start)
      ->where('created_at', '<', $period->endExclusive);
})->count();
```

### 6.3 Unchanged queries (no date condition)

```php
$offerCount = Offer::where('user_id', $user->id)->count();
$campaignCount = Campaign::whereHas('offer', fn ($q) => $q->where('user_id', $user->id))->count();
$activeCampaignCount = Campaign::whereHas('offer', fn ($q) => $q->where('user_id', $user->id))
    ->where('status', CampaignStatus::Active)->count();
```

### 6.4 Query count

| Metric | Queries |
|---|---|
| offer_count | 1 |
| campaign_count | 1 |
| active_campaign_count | 1 |
| click_count | 1 |
| conversion_count + revenue | 1 (combined) |
| total_expenses | 1 |
| **Total** | **7 queries** |

Bounded at ≤ 10 queries, independent of data size.

## 7. Form Request Design

### 7.1 DashboardStatisticsRequest

```php
class DashboardStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $period = $this->input('period');
        $isCustom = $period === 'custom';

        return [
            'period' => ['nullable', 'string', Rule::in(['today', 'last_7_days', 'last_30_days', 'this_month', 'custom'])],
            'from' => $isCustom
                ? ['required', 'date_format:Y-m-d', 'before_or_equal:to', 'before_or_equal:today']
                : ['prohibited'],
            'to' => $isCustom
                ? ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today']
                : ['prohibited'],
        ];
    }
}
```

## 8. API Endpoint Design

| Property | Value |
|----------|-------|
| Method | `GET` |
| URI | `/api/v1/dashboard/statistics` |
| Route name | `api.v1.dashboard.statistics` (unchanged) |
| Authentication | `auth:sanctum` |
| Controller | `DashboardStatisticsController::show` |
| Form Request | `DashboardStatisticsRequest` |

## 9. Blade Integration

### 9.1 Period selector UI

Compact `<form method="GET">` above statistics cards. `<select>` for periods, conditional date inputs for custom. Server-rendered, no JS dependency for basic behavior. URL-backed state.

### 9.2 Inventory vs. activity metrics

UI separates:
- **Current overview:** Offers, Campaigns, Active Campaigns (unfiltered)
- **Activity for selected period:** Clicks, Conversions, Revenue, Expenses, Profit

## 10. Ownership

All queries scoped to `$user->id` through ownership chain at database level. Period filtering adds date conditions within ownership-scoped queries. No post-query filtering.

## 11. Empty Period Behavior

Event metrics return zeros. Inventory metrics remain at their all-time values.

## 12. Financial Precision

Unchanged from KAN-18: `number_format((float) $value, 2, '.', '')`.

## 13. Postman/Newman

19-step flow covering all period modes, validation errors, and authorization.

## 14. Migration

No schema change required. Existing FK indexes sufficient at current scale.
