# Design - KAN-17: Gérer les dépenses d'une campagne

## 1. Existing Conventions Inspected

The design follows these implemented conventions:

- `routes/api.php`: `/api/v1` prefix, `auth:sanctum` grouping, named routes.
- `Campaign` model: `offer()` BelongsTo, `trackingLinks()` HasMany, `conversions()` HasMany, `CampaignStatus` enum cast.
- `Offer` model: `payout` DECIMAL(12,2), `user()` BelongsTo.
- `Conversion` model: `campaign()` BelongsTo, `revenue` DECIMAL(12,2).
- `ConversionController`: Thin controller, delegates to Action, returns `201` with `data.model` envelope.
- `RecordConversionAction`: Action pattern with `execute()` method.
- `StoreConversionRequest`: Form Request with `authorize()`, `rules()`.
- `ConversionResource`: JsonResource with `toArray()`.
- `CampaignPolicy`: Ownership derived through `Campaign → Offer → User`.
- Pest feature tests: `RefreshDatabase`, `Sanctum::actingAs()`, `assertDatabaseHas/Count/Missing`.
- API response envelope: `data.model_name`.
- Migration conventions: `foreignId()->constrained()->cascadeOnDelete()`, `DECIMAL(12,2)` for money.
- Validation conventions: `decimal:0,2`, `max:9999999999.99` for DECIMAL(12,2) fields.
- Pagination: `->paginate(15)` for list endpoints.
- Ordering: `->orderByDesc('id')` for list endpoints.
- `composer.json`: Laravel 13.8, PHP ^8.3, Pest ^4.7.

## 2. Data Model

### 2.1 `campaign_expenses` table

| Column | SQL/Laravel type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED`, `$table->id()` | No | auto increment | Primary key |
| `campaign_id` | `BIGINT UNSIGNED`, `$table->foreignId('campaign_id')` | No | none | FK to `campaigns.id` |
| `amount` | `DECIMAL(12,2)`, `$table->decimal('amount', 12, 2)` | No | none | Positive expense amount — no DB default |
| `spent_at` | `DATE`, `$table->date('spent_at')` | No | none | Client-controlled date — no DB default |
| `description` | `TEXT`, `$table->text('description')` | Yes | null | Optional metadata |
| `created_at` | `TIMESTAMP` | Yes | framework-managed | `$table->timestamps()` |
| `updated_at` | `TIMESTAMP` | Yes | framework-managed | `$table->timestamps()` |

Migration definition:

```php
$table->id();
$table->foreignId('campaign_id')
    ->constrained()
    ->cascadeOnDelete();
$table->decimal('amount', 12, 2);
$table->date('spent_at');
$table->text('description')->nullable();
$table->timestamps();
```

### 2.2 Schema Justification

**`campaign_id`** — FK to `campaigns.id` with `ON DELETE CASCADE`. An expense belongs to a Campaign, matching the MCD: `CAMPAGNE ||--o{ DEPENSE_CAMPAGNE : engage`. Deleting a Campaign removes its expense history.

**`amount`** — `DECIMAL(12,2)`, NOT NULL. The expense amount in the campaign's currency. Uses `DECIMAL(12,2)` for consistency with other money fields (`budget`, `payout`, `revenue`). No DB default — application must supply trusted value. Must be positive (`gt:0`).

**`spent_at`** — `DATE`, NOT NULL. The date the expense was incurred. Client-controlled for historical expense entry. Application validates that the date is not in the future.

**`description`** — `TEXT`, NULLABLE. Optional human-readable description of the expense. Max 10000 characters.

**No `user_id`, `offer_id`** — Ownership is derived through `CampaignExpense → Campaign → Offer → User`.

**No `category`, `type`, `source`, `reference`, `status`, `deleted_at`** — Not in MLD. Can be added in a future story.

## 3. Relationship Design

```
Campaign → CampaignExpense: HasMany
CampaignExpense → Campaign: BelongsTo
```

- `Campaign::expenses()` — HasMany (short name for idiomatic nested resource semantics)
- `CampaignExpense::campaign()` — BelongsTo

No additional relationships needed. Ownership derived through `Campaign → Offer → User`.

## 4. Nested-Resource Security

### 4.1 Threat Model

A globally route-bound Expense must NEVER be accepted merely because the parent Campaign belongs to the authenticated user. Consider:

- Attacker owns Campaign A
- Expense X belongs to Campaign B (owned by another user)
- Attacker requests: `PATCH /campaigns/A/expenses/X`
- Without scoped resolution, Expense X could be modified/deleted

### 4.2 Protection Mechanism

**Scoped nested resolution through the Campaign relationship.**

Every endpoint that resolves an Expense must use:

```php
$expense = $campaign->expenses()->findOrFail($expenseId);
```

This guarantees that:
1. The Expense exists
2. The Expense belongs to the route-bound Campaign
3. Campaign/Expense mismatch → `404 Not Found`

This is enforced in the Controller, not in the Action or Policy. The Policy only verifies Campaign-level ownership. Child membership is enforced independently through scoped nested resolution.

### 4.3 Security Test Scenarios

| Scenario | Expected |
|----------|----------|
| Owned Campaign + Expense from same Campaign | 200/204 |
| Owned Campaign + Expense from another owned Campaign | 404 |
| Owned Campaign + Expense from foreign user's Campaign | 404 |

## 5. Financial Integrity

| Rule | Decision |
|------|----------|
| Amount must be positive | Yes — `gt:0` validation |
| Zero amount allowed | No — not a meaningful business transaction |
| Negative amount allowed | No — expenses cannot be negative |
| Decimal precision | `DECIMAL(12,2)` — consistent with existing money fields |
| Amount editable after creation | Yes — via PATCH endpoint |
| Deletion allowed | Yes — via DELETE endpoint |
| Future dates allowed | No — `spent_at` must be ≤ today |
| Client controls `spent_at` | Yes — historical expense entry expected |
| Amount contributes to profit | Yes — future KAN-18 will use `SUM(expenses.amount)` |

## 6. Budget Relationship

`Campaign.budget` remains **informational**. KAN-17 does NOT enforce:

- Cumulative expenses ≤ budget.
- Budget remaining calculations.
- Budget alerts.

Rationale: KAN-18 statistics will own aggregation. Budget enforcement is a separate business decision not yet defined. The safest minimal behavior is to record expenses accurately and let future stories handle budget logic.

## 7. Endpoint Design

### 7.1 Create Expense

| Property | Value |
|----------|-------|
| Method | `POST` |
| URI | `/api/v1/campaigns/{campaign}/expenses` |
| Route name | `api.v1.campaigns.expenses.store` |
| Authentication | `auth:sanctum` |
| Authorization | `CampaignPolicy::recordExpense` |
| Request | `StoreCampaignExpenseRequest` |
| Response | `201` with `data.campaign_expense` |
| Errors | `401`, `403`, `404`, `422` |

### 7.2 List Expenses

| Property | Value |
|----------|-------|
| Method | `GET` |
| URI | `/api/v1/campaigns/{campaign}/expenses` |
| Route name | `api.v1.campaigns.expenses.index` |
| Authentication | `auth:sanctum` |
| Authorization | `CampaignPolicy::viewExpenses` |
| Request | None |
| Response | `200` with paginated `data.campaign_expenses` |
| Pagination | 15 per page |
| Ordering | `spent_at DESC`, then `id DESC` |
| Errors | `401`, `403`, `404` |

### 7.3 Update Expense

| Property | Value |
|----------|-------|
| Method | `PATCH` |
| URI | `/api/v1/campaigns/{campaign}/expenses/{expense}` |
| Route name | `api.v1.campaigns.expenses.update` |
| Authentication | `auth:sanctum` |
| Authorization | `CampaignPolicy::updateExpense` + scoped resolution |
| Request | `UpdateCampaignExpenseRequest` |
| Response | `200` with `data.campaign_expense` |
| Errors | `401`, `403`, `404`, `422` |

### 7.4 Delete Expense

| Property | Value |
|----------|-------|
| Method | `DELETE` |
| URI | `/api/v1/campaigns/{campaign}/expenses/{expense}` |
| Route name | `api.v1.campaigns.expenses.destroy` |
| Authentication | `auth:sanctum` |
| Authorization | `CampaignPolicy::deleteExpense` + scoped resolution |
| Request | None |
| Response | `204` (no content) |
| Errors | `401`, `403`, `404` |

## 8. Authorization Strategy

Extend `CampaignPolicy` with new methods:

- `viewExpenses(User, Campaign)` — owner check via `ownsCampaign`
- `recordExpense(User, Campaign)` — owner check via `ownsCampaign`
- `updateExpense(User, Campaign)` — owner check via `ownsCampaign`
- `deleteExpense(User, Campaign)` — owner check via `ownsCampaign`

All use the existing `ownsCampaign` private method. No `ExpensePolicy` needed — expenses are fundamentally scoped to their Campaign.

**Critical:** Policy only verifies Campaign-level ownership. Child Expense membership is enforced independently through scoped nested resolution in the Controller.

## 9. Validation Rules

### StoreCampaignExpenseRequest

```php
'amount' => [
    'required',
    'numeric',
    'gt:0',
    'max:9999999999.99',
    'decimal:0,2',
],
'spent_at' => [
    'required',
    'date',
    'before_or_equal:today',
],
'description' => [
    'nullable',
    'string',
    'max:10000',
],
'campaign_id' => ['prohibited'],
```

### UpdateCampaignExpenseRequest

```php
'amount' => [
    'sometimes',
    'required',
    'numeric',
    'gt:0',
    'max:9999999999.99',
    'decimal:0,2',
],
'spent_at' => [
    'sometimes',
    'required',
    'date',
    'before_or_equal:today',
],
'description' => [
    'sometimes',
    'nullable',
    'string',
    'max:10000',
],
```

### Amount Validation Details

- `numeric` — must be a number
- `gt:0` — strictly greater than zero
- `max:9999999999.99` — fits inside DECIMAL(12,2) range
- `decimal:0,2` — maximum 2 decimal places (rejects values like `12.345`)

This matches the existing project convention used for `budget` in `StoreCampaignRequest`.

## 10. Action Design

### RecordCampaignExpenseAction

```php
public function execute(
    Campaign $campaign,
    float $amount,
    string $spentAt,
    ?string $description = null,
): CampaignExpense {
    return $campaign->expenses()->create([
        'amount' => $amount,
        'spent_at' => $spentAt,
        'description' => $description,
    ]);
}
```

### UpdateCampaignExpenseAction

```php
public function execute(
    CampaignExpense $expense,
    array $fields,
): CampaignExpense {
    $expense->update($fields);
    return $expense;
}
```

### DeleteCampaignExpenseAction

```php
public function execute(CampaignExpense $expense): void
{
    $expense->delete();
}
```

**Note:** Actions operate only on a CampaignExpense already proven to belong to the route Campaign through scoped nested resolution in the Controller. Actions do not perform HTTP authorization or duplicate child-parent security checks.

## 11. Resource Design

```php
class CampaignExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'spent_at' => $this->spent_at->toDateString(),
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

## 12. Date/Time Trust Model

- `spent_at` is **client-controlled** — the user provides the date the expense was incurred.
- Server validates `before_or_equal:today` to reject future dates.
- `spent_at` is stored as `DATE` (not `TIMESTAMP`) — time component is not relevant for expenses.
- Historical expense entry is supported (user can enter expenses for past dates).
- `created_at` / `updated_at` are framework-managed timestamps — client cannot control them.

## 13. Aggregation Boundaries

KAN-17 records and manages individual expenses. It does NOT implement:

- `SUM(expenses.amount)` aggregation.
- Profit calculation (`approved revenue - expenses`).
- ROI / ROAS.
- Period-based statistics.
- Dashboard cards.
- Summary materialized columns.
- Cached totals.

These belong to KAN-18 statistics. KAN-17 provides the raw data that KAN-18 will aggregate.
