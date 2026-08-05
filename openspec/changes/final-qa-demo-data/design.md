# Design — KAN-23: Final QA, Test Stability & Demo Data

## 1. Flaky Test Root Cause & Fix

### Root Cause

`CampaignWebTest::success flash renders after campaign_creation` (line 246):
```php
$offer = Offer::factory()->for($this->user)->create();
```

`OfferFactory::definition()` uses `fake()->randomElement(OfferStatus::cases())` for `status`. When `Archived` is randomly selected (~25% probability), the `StoreCampaignRequest::after()` hook rejects the offer:

```php
if ($this->resolvedOffer->status === OfferStatus::Archived) {
    $validator->errors()->add('offer_id', 'An archived offer cannot be used to create a campaign.');
}
```

### Fix

Change line 246 from:
```php
$offer = Offer::factory()->for($this->user)->create();
```
to:
```php
$offer = Offer::factory()->for($this->user)->draft()->create();
```

This matches the pattern already used at line 65 of the same file.

## 2. Factory Default State Fix

### Problem

`OfferFactory::definition()` generates a random status from all 4 `OfferStatus` cases. This means every factory invocation without an explicit state gets a random offer status, creating nondeterminism.

### Fix

Change `OfferFactory::definition()`:
```php
// Before
'status' => fake()->randomElement(OfferStatus::cases()),

// After
'status' => OfferStatus::Draft,
```

A default Offer should be a Draft — the valid neutral state. Tests that need specific statuses use explicit states (`->active()`, `->archived()`, etc.).

### Impact on Existing Tests

Verified: **zero tests break** from this change. 90+ tests create offers without explicit status, but none assert on or depend on a specific Offer status value. All status-asserting tests already use explicit states.

### Add Missing States

```php
public function suspended(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => OfferStatus::Suspended,
    ]);
}

public function archived(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => OfferStatus::Archived,
    ]);
}
```

## 3. ConversionFactory Side-Effect Fix

### Problem

`ConversionFactory::definition()` eagerly creates a Campaign+Offer+User inside `definition()`:
```php
'campaign_id' => Campaign::factory()->create(['status' => CampaignStatus::Active])->id,
```

This means `->make()` still hits the database, which violates factory contract.

### Fix

Use lazy factory reference:
```php
'campaign_id' => Campaign::factory()->active(),
```

The actual `create()` happens when the Conversion is created. Same pattern for `CampaignExpenseFactory`.

## 4. UserFactory Explicit Role

### Problem

`UserFactory::definition()` doesn't set `role`. It relies on the DB default (`affiliate`). This works but is fragile.

### Fix

Add explicit role:
```php
'role' => UserRole::Affiliate,
```

## 5. Timing Test Hardening

### Problem

`ConversionApiTest::generates converted_at as approximately the current time` uses a 1-second tolerance:
```php
->greaterThanOrEqualTo($before->subSecond())
->lessThanOrEqualTo($after->addSecond());
```

### Fix

Widen to 5-second tolerance, matching other timing tests in the codebase:
```php
->greaterThanOrEqualTo($before->subSeconds(5))
->lessThanOrEqualTo($after->addSeconds(5));
```

## 6. FK Index Audit — No Migration Needed

### Investigation

Inspected actual MySQL schema via `SHOW INDEX` on `cpaflow_ai` database:

| Table | FK Column | Index Exists | Index Name |
|-------|-----------|-------------|------------|
| `campaigns` | `offer_id` | YES | `campaigns_offer_id_foreign` |
| `tracking_clicks` | `tracking_link_id` | YES | `tracking_clicks_tracking_link_id_foreign` |
| `conversions` | `campaign_id` | YES | `conversions_campaign_id_foreign` |
| `campaign_expenses` | `campaign_id` | YES | `campaign_expenses_campaign_id_foreign` |
| `ai_generations` | `offer_id` | YES | `ai_generations_offer_id_foreign` |

### Conclusion

MySQL/InnoDB automatically creates supporting indexes for foreign key constraints defined via `foreignId()->constrained()`. No additive migration is required.

**No migration will be created for KAN-23.**

## 7. Demo Seeder Architecture

### Design

```
DatabaseSeeder (unchanged — creates test@example.com only)
  ↓ NOT called by
DemoDataSeeder (new — invoked manually)
  php artisan db:seed --class=DemoDataSeeder
```

### Production Safety

```php
public function run(): void
{
    if (app()->environment('production')) {
        throw new \RuntimeException(
            'DemoDataSeeder refused: DEMO ONLY — NEVER PRODUCTION CREDENTIALS. '
            .'This seeder creates test accounts (admin@example.test, affiliate@example.test) '
            .'that must never exist in production.'
        );
    }
    // ... seeding logic
}
```

### Idempotency

Use `updateOrCreate` with deterministic identifiers for every record type:

| Record | Deterministic Identifier |
|--------|------------------------|
| Users | `email` |
| Offers | `user_id` + `name` (unmistakable demo names) |
| Campaigns | `offer_id` + `name` |
| TrackingLinks | Fixed deterministic 32-char demo code |
| TrackingClicks | `tracking_link_id` + UTM marker (`demo-click-1`, etc.) |
| Conversions | Fixed deterministic `external_id` values |
| CampaignExpenses | `campaign_id` + deterministic description marker |
| AiAnalysis | `offer_id` (one per offer, HasOne) |
| AiGeneration | `offer_id` (using completed analysis) |

Re-running creates no duplicates. No unrelated records are deleted.

## 8. Demo Dataset Graph

```
Admin (admin@example.test, admin)
  ↓ manages (not owner of any business data)

Affiliate (affiliate@example.test, affiliate)
  ├── Offer 1 "Demo CPA Offer" (active, $25.00 payout)
  │   ├── Campaign 1 "Demo Campaign" (active)
  │   │   └── TrackingLink 1 (deterministic code)
  │   │       └── TrackingClick ×3
  │   │           ├── demo-click-1 (today)
  │   │           ├── demo-click-2 (today, start of day)
  │   │           └── demo-click-3 (yesterday)
  │   ├── Conversion 1 (approved, $25.00, today)
  │   ├── Conversion 2 (approved, $25.00, yesterday)
  │   ├── Conversion 3 (pending, $25.00, today)
  │   ├── CampaignExpense 1 ($40.00, yesterday)
  │   ├── CampaignExpense 2 ($30.00, today)
  │   ├── AiAnalysis (completed, score 85)
  │   └── AiGeneration (completed, hooks + captions)
  ├── Offer 2 "Demo Draft Offer" (draft, $10.00 payout)
  │   └── Campaign 2 "Demo Draft Campaign" (draft)
  └── Offer 3 "Demo Archived Offer" (archived, $15.00 payout)

Affiliate2 (affiliate2@example.test, affiliate)
  (empty — safe target for admin role-change demo)
```

## 9. Demo Financial Values

| Metric | Value | Breakdown |
|--------|-------|-----------|
| Approved Revenue | $50.00 | 2 × $25.00 (approved conversions) |
| Pending Revenue | $25.00 | 1 × $25.00 (pending, NOT counted in revenue) |
| Total Conversions | 3 | All statuses counted |
| Expenses | $70.00 | $40.00 + $30.00 |
| Profit | -$20.00 | $50.00 - $70.00 |

### Dashboard Expectations (All-Time)

| Field | Expected |
|-------|----------|
| `offer_count` | 3 |
| `campaign_count` | 2 |
| `active_campaign_count` | 1 |
| `click_count` | 3 |
| `conversion_count` | 3 |
| `revenue` | 50.00 |
| `total_expenses` | 70.00 |
| `profit` | -20.00 |

The Pest test will verify these via `GetDashboardStatisticsAction::execute()` — not hardcoded assertions.

## 10. Period-Boundary Date Strategy

### Problem

Naive `now()->subDays(5)` on August 5 produces July 31, which falls outside `this_month`.

### Solution

Use explicit relative dates anchored to calendar boundaries:

| Record | Timestamp | Rationale |
|--------|-----------|-----------|
| TrackingClick 1 | `now()` (today) | Included in `today`, `this_month`, `last_7_days`, `last_30_days` |
| TrackingClick 2 | `today()->startOfDay()` (today midnight) | Included in `today` (start of day) |
| TrackingClick 3 | `today()->subDay()` (yesterday) | NOT in `today`, in `this_month`, `last_7_days`, `last_30_days` |
| Conversion 1 | `now()` (today) | Included in `today`, `this_month` |
| Conversion 2 | `today()->subDay()` (yesterday) | NOT in `today`, in `this_month`, `last_7_days` |
| Conversion 3 | `today()->subDay()` (yesterday, pending) | Same as above |
| Expense 1 | `today()->subDay()` (yesterday) | NOT in `today`, in `this_month`, `last_7_days` |
| Expense 2 | `now()` (today) | Included in `today`, `this_month` |

### Expected Period Totals

| Period | Clicks | Conversions | Revenue | Expenses | Profit |
|--------|--------|-------------|---------|----------|--------|
| all_time | 3 | 3 | 50.00 | 70.00 | -20.00 |
| today | 2 | 2 | 25.00 | 30.00 | -5.00 |
| this_month | 3 | 3 | 50.00 | 70.00 | -20.00 |
| last_7_days | 3 | 3 | 50.00 | 70.00 | -20.00 |

**Note:** `today` counts only records with timestamps from `startOfDay()` onward. `this_month` includes everything from `startOfMonth()` onward. Since all demo data is created within the current month and the last 2 days, `this_month` and `last_7_days` totals equal `all_time`.

The Pest test will verify these by calling `GetDashboardStatisticsAction` with the appropriate period parameter.

## 11. AI Demo Data Strategy

### Hash Consistency

Seeded AI records use the same canonical snapshots and hashers as production:

**AiAnalysis:**
```php
$offer = Offer::where('name', 'Demo CPA Offer')->first();
$snapshot = OfferAiInputSnapshot::fromOffer($offer);
$hash = app(OfferInputHasher::class)->compute($snapshot);

AiAnalysis::create([
    'offer_id' => $offer->id,
    'status' => AiProcessStatus::Completed,
    'score' => 85,
    'summary' => 'Strong CPA offer with competitive payout...',
    'strengths' => ['High conversion potential', 'Clear targeting'],
    'weaknesses' => ['Limited traffic source diversity'],
    'recommendations' => ['Test with TikTok traffic', 'Add retargeting pixel'],
    'input_hash' => $hash,
    'provider' => 'openai',
    'model' => 'gpt-4o',
    'completed_at' => now(),
]);
```

**AiGeneration:**
```php
$analysis = $offer->analysis; // just created above
$genSnapshot = OfferContentGenerationSnapshot::fromOfferAndAnalysis($offer, $analysis);
$genHash = app(GenerationInputHasher::class)->compute($genSnapshot);

AiGeneration::create([
    'offer_id' => $offer->id,
    'status' => AiProcessStatus::Completed,
    'hooks' => ['Stop scrolling!', 'This offer pays $25 per lead...', 'Limited time CPA deal'],
    'captions' => ['Earn money with this high-converting CPA offer. Simple process, fast payouts.'],
    'input_hash' => $genHash,
    'provider' => 'openai',
    'model' => 'gpt-4o',
    'completed_at' => now(),
]);
```

### Non-Stale Guarantee

The seeded records satisfy the same non-stale rules as live records because:
1. The Offer is created first with deterministic values
2. The snapshot is computed from the final persisted Offer data
3. The hash matches what `GetGenerationAction` would compute
4. No Prism/OpenAI calls, no queues, no external dependencies

## 12. Admin Demo Strategy

### Demo Accounts

| Account | Email | Password | Role | Purpose |
|---------|-------|----------|------|---------|
| Admin | `admin@example.test` | `password` | admin | KAN-22 demo (list, show, updateRole) |
| Affiliate | `affiliate@example.test` | `password` | affiliate | Primary demo account with business data |
| Affiliate2 | `affiliate2@example.test` | `password` | affiliate | Safe target for admin role-change demo |

### Demo Safety

- Admin role change demo targets `affiliate2@example.test` — primary affiliate remains intact
- `affiliate@example.test` owns all business data — changing its role would break demo

## 13. Conversion Approval Gap

### Current State

- `ConversionStatus` enum: `Pending`, `Approved`, `Rejected`
- `RecordConversionAction` always creates as `Pending`
- `GetDashboardStatisticsAction` only counts `Approved` for revenue
- **No HTTP endpoint exists to transition Pending → Approved/Rejected**

### Classification

**NOT A KAN-23 IMPLEMENTATION BLOCKER**

**PROJECT RELEASE / FUNCTIONAL-COMPLETENESS BLOCKER**

Reason: Without a status-transition workflow, a real user's normally recorded conversions can never contribute to approved revenue/profit through the normal application API. Seeded Approved conversions demonstrate dashboard behavior but do not solve the actual product workflow.

### Recommendation

Create a separate story immediately after KAN-23 and before final delivery/deployment. That future story must decide:
- Who approves/rejects conversions (admin-only? campaign owner?)
- Allowed state transitions
- Authorization rules
- Endpoints
- Audit/idempotency behavior
- Impact on dashboard

## 14. Postman Collection Strategy

### Prerequisites

```bash
php artisan db:seed --class=DemoDataSeeder
```

### Collection Flow

| Step | Request | Auth | Expected |
|------|---------|------|----------|
| 1 | `GET /api/v1/health` | None | 200 |
| 2 | `POST /api/v1/auth/register` (unique QA email) | None | 201 |
| 3 | `POST /api/v1/auth/login` (QA user) | None | 200 + token |
| 4 | `POST /api/v1/offers` | QA affiliate | 201 |
| 5 | `POST /api/v1/campaigns` | QA affiliate | 201 |
| 6 | `POST /api/v1/campaigns/{id}/activate` | QA affiliate | 200 |
| 7 | `POST /api/v1/campaigns/{id}/tracking-links` | QA affiliate | 201 |
| 8 | `POST /api/v1/campaigns/{id}/conversions` | QA affiliate | 201 |
| 9 | `POST /api/v1/campaigns/{id}/expenses` | QA affiliate | 201 |
| 10 | `GET /api/v1/dashboard/statistics` | QA affiliate | 200 |
| 11 | `GET /api/v1/offers/{id}/analysis` | Seeded affiliate | 200 (existing demo) |
| 12 | `GET /api/v1/offers/{id}/generations` | Seeded affiliate | 200 (existing demo) |
| 13 | `POST /api/v1/auth/login` (admin) | None | 200 + admin token |
| 14 | `GET /api/v1/admin/users` | Admin | 200 |
| 15 | `GET /api/v1/admin/users/{id}` | Admin | 200 |
| 16 | `GET /api/v1/admin/users` | QA affiliate | 403 |
| 17 | `GET /api/v1/admin/users` | None | 401 |

### Newman Repeatability

- QA affiliate email generated per run: `kan23-qa-{timestamp}@example.test`
- No stale variable dependencies
- No manual DB editing required
- No real AI calls
- Mutating E2E flow uses separate QA user — seeded dashboard unchanged

## 15. DemoDataSeeder Test Coverage

### Test File

`tests/Feature/Database/DemoDataSeederTest.php`

### Test Cases

| Test | Assertion |
|------|-----------|
| seeder creates admin demo account | `assertDatabaseHas('users', ['email' => 'admin@example.test', 'role' => 'admin'])` |
| seeder creates affiliate demo account | `assertDatabaseHas('users', ['email' => 'affiliate@example.test', 'role' => 'affiliate'])` |
| seeder creates affiliate2 demo account | `assertDatabaseHas('users', ['email' => 'affiliate2@example.test', 'role' => 'affiliate'])` |
| seeder creates demo offers | Count = 3 for demo affiliate |
| seeder creates demo campaigns | Count = 2 |
| seeder creates demo tracking link | Count = 1 on active campaign |
| seeder creates demo clicks | Count = 3 |
| seeder creates demo conversions | Count = 3 |
| seeder creates demo expenses | Count = 2 |
| seeder creates completed ai analysis | `status = completed`, `score = 85`, `input_hash` matches production hasher |
| seeder creates completed ai generation | `status = completed`, `hooks/captions` present, `input_hash` matches production hasher |
| seeder is idempotent | Run twice → identical record counts |
| seeder idempotent dashboard totals | Run twice → identical `GetDashboardStatisticsAction` results |
| seeder refuses production | `app()->environment('production')` → exception thrown |
| no provider/network/queue calls | No Prism, no OpenAI, no Queue::dispatched |

## 16. Blade QA

### Verified (no issues)

- Pages render without Vite dependency (`$this->withoutVite()` in TestCase)
- Flash messages work (after flaky test fix)
- Empty states do not crash
- Forms point to valid routes
- Navigation links present
- Dashboard renders without analytics

### No changes needed

KAN-31 Phase 1 is sufficient. Visual redesign is a separate story.

## 17. Security Regression

### Verified intact

- Guests → 401 on all auth:sanctum routes
- Ownership → 403 on foreign offers/campaigns
- Nested wrong-parent → 404
- Mass-assignment: `role` not in `User::$fillable`
- Admin cannot bypass Offer/Campaign ownership (tested in KAN-22)
- Registration cannot choose admin (tested)
- Sensitive fields not serialized (`#[Hidden]`)
- No raw IP storage (HMAC-SHA256 hashing)
- No AI provider errors exposed to client
- No API keys committed
- No passwords in demo docs (use `password` as demo password, clearly marked DEMO ONLY)
