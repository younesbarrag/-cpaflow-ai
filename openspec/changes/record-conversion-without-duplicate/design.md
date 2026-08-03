# Design - KAN-16: Enregistrer une conversion sans doublon

## 1. Existing Conventions Inspected

The design follows these implemented conventions:

- `routes/api.php`: `/api/v1` prefix, `auth:sanctum` grouping, named routes.
- `Campaign` model: `offer()` BelongsTo, `trackingLinks()` HasMany, `CampaignStatus` enum cast.
- `Offer` model: `payout` DECIMAL(12,2), `user()` BelongsTo.
- `ConversionStatus` enum: `Pending`, `Approved`, `Rejected` cases (created KAN-8, not yet linked).
- `bootstrap/app.php`: Exception rendering for domain exceptions (`InvalidCampaignTransition` → 409).
- `GenerateTrackingLinkAction`: Action pattern with `execute()` method, catches `UniqueConstraintViolationException`.
- `GenerateTrackingLinkRequest`: Form Request with `authorize()`, empty `rules()`, `after()` hook for Campaign status.
- Pest feature tests: `RefreshDatabase`, `Sanctum::actingAs()`, `assertDatabaseHas/Count/Missing`.
- API response envelope: `data.model_name`.
- Ownership patterns: `OfferPolicy`, `CampaignPolicy`, derived through relationships.
- `composer.json`: Laravel 13.8, PHP ^8.3, Pest ^4.7.

## 2. Data Model

### 2.1 `conversions` table

| Column | SQL/Laravel type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED`, `$table->id()` | No | auto increment | Primary key |
| `campaign_id` | `BIGINT UNSIGNED`, `$table->foreignId('campaign_id')` | No | none | FK to `campaigns.id` |
| `external_id` | `VARCHAR(255)`, `$table->string('external_id', 255)` | No | none | UNIQUE — advertiser transaction ID |
| `source` | `VARCHAR(255)`, `$table->string('source', 255)` | Yes | null | Informational — where conversion came from |
| `revenue` | `DECIMAL(12,2)`, `$table->decimal('revenue', 12, 2)` | No | none | Snapshotted from Offer.payout — no DB default; application must supply trusted value |
| `status` | `VARCHAR(20)`, `$table->string('status', 20)` | No | pending | Enum cast, INDEX |
| `converted_at` | `TIMESTAMP`, `$table->timestamp('converted_at')` | No | none | No DB default — application explicitly sets `now()` during creation |
| `created_at` | `TIMESTAMP` | Yes | framework-managed | `$table->timestamps()` |
| `updated_at` | `TIMESTAMP` | Yes | framework-managed | `$table->timestamps()` |

Migration definition:

```php
$table->id();
$table->foreignId('campaign_id')
    ->constrained()
    ->cascadeOnDelete();
$table->string('external_id', 255)->unique();
$table->string('source', 255)->nullable();
$table->decimal('revenue', 12, 2);
$table->string('status', 20)
    ->default(ConversionStatus::Pending->value)
    ->index();
$table->timestamp('converted_at');
$table->timestamps();
```

This is an additive `create_conversions_table` migration. No destructive command or schema operation is permitted.

### 2.2 Schema Justification

**`campaign_id`** — FK to `campaigns.id` with `ON DELETE CASCADE`. A conversion belongs to a Campaign, matching the MCD: `CAMPAGNE ||--o{ CONVERSION : recoit`. Deleting a Campaign removes its conversion history. This matches the existing cascade pattern: `offers → campaigns → conversions`.

**`external_id`** — VARCHAR(255), NOT NULL, UNIQUE. The advertiser's transaction identifier. The UNIQUE constraint prevents the same logical conversion from being recorded twice. NOT NULL ensures the constraint is effective (MySQL allows multiple NULLs in a UNIQUE column). This is the primary dedup mechanism per business rule R4. The NOT NULL design intentionally refines the older nullable MLD because KAN-16 requires deterministic duplicate prevention.

**`source`** — VARCHAR(255), nullable. Informational field indicating where the conversion came from (e.g., "postback", "manual"). Exists in the documented MCD/MLD but its exact semantics are not yet defined for KAN-16. Nullable because it is optional and informational only.

**`revenue`** — DECIMAL(12,2), NOT NULL, DEFAULT 0.00. Snapshotted from `Offer.payout` at conversion time using the MCD/MLD term `revenue`. This ensures historical conversions retain the revenue value even if the Offer payout changes later. The client cannot submit this value — it is derived server-side.

**`status`** — VARCHAR(20), NOT NULL, DEFAULT 'pending'. Uses the existing `ConversionStatus` enum. New conversions start as `pending`. Status transitions (approve/reject) are out of KAN-16 scope.

**`converted_at`** — TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP. Server-generated when the conversion is successfully recorded. Not accepted from the client to avoid authenticated clients forging analytics periods and to preserve trust for future KAN-18 period statistics.

### 2.3 Fields deliberately excluded

| Excluded field | Reason |
|---|---|
| `tracking_link_id` | Not in KAN-16 scope — tracking attribution is outside this story |
| `tracking_click_id` | Not in KAN-16 scope — click attribution is outside this story |
| `offer_id` | Redundant — derivable through `Campaign → Offer` |
| `user_id` | Redundant — derivable through `Campaign → Offer → User` |
| `payout` | Not used — the MCD/MLD field is `revenue`, not `payout`; payout is the source value, revenue is the snapshot |
| `ip_hash` | Not applicable to conversion postbacks |
| `clicked_at` | Not applicable to conversions |
| `conversion_count` on campaigns | Denormalized counter; unnecessary with a conversions table |
| Soft deletes | Not in KAN-16 scope |

### 2.4 Foreign key and indexes

- `campaign_id → campaigns.id` uses `ON DELETE CASCADE`, preventing orphan conversions if a campaign is deleted.
- `external_id` has a UNIQUE constraint — the primary dedup mechanism.
- `status` has an INDEX for filtering conversions by status (future queries).
- `id` is the deterministic sort key and primary index.

## 3. Eloquent Design

### 3.1 `Conversion`

- `$fillable`: `campaign_id`, `external_id`, `source`, `revenue`, `status`, `converted_at`.
- `$casts`: `revenue` → `'decimal:2'`, `status` → `ConversionStatus::class`, `converted_at` → `'datetime'`.
- `campaign(): BelongsTo`.
- No `Offer`, `User`, `TrackingLink`, or `TrackingClick` direct relationships — ownership is derived through `Campaign`.

### 3.2 Relationship additions

- `Campaign::conversions(): HasMany`.
- `Conversion::campaign(): BelongsTo`.
- `ConversionFactory` creates a Campaign by default and provides a state/helper for explicit Campaign assignment.
- No `Offer::conversions()` or `User::conversions()` has-many-through relationship is implemented. KAN-16 has no listing endpoint that requires cross-aggregate queries.

## 4. Duplicate Prevention

### 4.1 Duplicate identity

The `external_id` field is the unique identifier for a conversion. The business rule R4 states: "Le champ `external_id` sur les conversions empêche les doublons lors des postbacks."

### 4.2 Database UNIQUE constraint

```php
$table->unique('external_id');
```

This is the final protection against duplicates. Two rows with the same `external_id` cannot coexist in the database, regardless of application-level logic.

### 4.3 NOT NULL enforcement

`external_id` is NOT NULL. This is critical because MySQL allows multiple NULLs in a UNIQUE column. By requiring `external_id`, the UNIQUE constraint is always effective.

### 4.4 First request behavior

1. Action attempts `INSERT` with the provided `external_id`.
2. If no existing row has this `external_id`, the INSERT succeeds.
3. Controller returns `201 Created` with the new Conversion.

### 4.5 Duplicate request behavior

1. Action attempts `INSERT` with the same `external_id`.
2. MySQL rejects the INSERT due to UNIQUE constraint violation.
3. Laravel wraps this in `Illuminate\Database\UniqueConstraintViolationException`.
4. `RecordConversionAction` catches this exception **only if the collision is specifically on the `external_id` column** — any other `UniqueConstraintViolationException` is rethrown. The action throws `DuplicateConversionException`.
5. `bootstrap/app.php` exception handler catches `DuplicateConversionException` and returns `409 Conflict` JSON response.
6. No second row is created.

### 4.6 Concurrent request behavior

Two simultaneous requests with the same `external_id`:
1. Both attempt INSERT at approximately the same time.
2. One INSERT succeeds (gets the row lock first).
3. The other INSERT fails with UNIQUE constraint violation.
4. The failing request receives `409 Conflict`.
5. The database guarantees exactly one row with this `external_id`.

The UNIQUE constraint operates at the database engine level and is not affected by application-level race conditions.

### 4.7 Different conversion for the same Campaign

A different `external_id` for the same Campaign creates a new conversion. Multiple conversions per Campaign are allowed — they represent different advertiser transactions.

### 4.8 Unknown Campaign

If the route-bound Campaign does not exist, Laravel's implicit model binding returns `404 Not Found` before the Form Request is evaluated.

### 4.9 Invalid identifiers

If `external_id` is missing or empty, the `StoreConversionRequest` validation returns `422` with `errors.external_id`.

### 4.10 No Form Request unique rule

The `StoreConversionRequest` does NOT use Laravel's `unique` validation rule on `external_id`. Using `unique:conversions,external_id` in the Form Request would return `422` before the Action executes, contradicting the approved duplicate behavior of `409 Conflict`. Uniqueness is enforced exclusively at the database level via the UNIQUE constraint, and the Action catches `UniqueConstraintViolationException`.

## 5. Endpoint Design

### 5.1 Route

| Method | URI | Route name |
|---|---|---|
| POST | `/api/v1/campaigns/{campaign}/conversions` | `api.v1.campaigns.conversions.store` |

Route is under `/api/v1` and protected by `auth:sanctum`.

### 5.2 Authentication/Trust Model

The endpoint uses `auth:sanctum` — the authenticated user must own the Campaign through `Campaign → Offer → User`. This is consistent with the existing API authentication pattern.

The client submits `external_id` (the advertiser's transaction ID) and optionally `source`. The `revenue` is derived server-side from the Offer. The `converted_at` is server-generated.

### 5.3 Request Payload

```json
{
    "external_id": "TXN-2026-001234",
    "source": "postback"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `external_id` | string | Yes | Advertiser transaction ID, max 255 chars, unique at DB level |
| `source` | string | No | Max 255 chars, nullable, informational only |

`campaign_id` is NOT in the request body — it comes from the route parameter `{campaign}`.
`revenue` is NOT in the request body — it is derived server-side from Offer.payout.
`converted_at` is NOT in the request body — it is server-generated as `now()`.

### 5.4 Success Response (201)

```json
{
    "data": {
        "conversion": {
            "id": 1,
            "campaign_id": 1,
            "external_id": "TXN-2026-001234",
            "source": "postback",
            "revenue": "25.00",
            "status": "pending",
            "converted_at": "2026-08-03T10:30:01.000000Z",
            "created_at": "2026-08-03T10:30:01.000000Z",
            "updated_at": "2026-08-03T10:30:01.000000Z"
        }
    }
}
```

### 5.5 Duplicate Response (409)

```json
{
    "message": "A conversion with this external ID already exists.",
    "errors": {
        "external_id": ["A conversion with this external ID already exists."]
    }
}
```

### 5.6 Validation Error Response (422)

```json
{
    "message": "The provided data was invalid.",
    "errors": {
        "external_id": ["The external id field is required."]
    }
}
```

### 5.7 Foreign Ownership Response (403)

Standard Laravel 403 response. The user does not own the Campaign.

### 5.8 Unknown Campaign Response (404)

Standard Laravel 404 response. The Campaign does not exist.

## 6. Architecture

### 6.1 Component responsibilities

| Component | Responsibility |
|---|---|
| `ConversionController` | Resolve Campaign via route model binding, call Action, return response |
| `RecordConversionAction` | Load Campaign→Offer chain, snapshot revenue, persist Conversion, catch unique violation |
| `Conversion` model | Eloquent representation of a conversion record |
| `StoreConversionRequest` | Validate `external_id` and `source`, authorize ownership |
| `ConversionResource` | Serialize Conversion for API response |
| `CampaignPolicy::recordConversion` | Verify user owns the Campaign |
| `DuplicateConversionException` | Domain exception for duplicate `external_id` |
| `Campaign::conversions()` | HasMany relationship to Conversion |
| `Conversion::campaign()` | BelongsTo relationship to Campaign |
| `ConversionFactory` | Test data generation |

### 6.2 Controller design

```php
class ConversionController extends Controller
{
    public function store(
        StoreConversionRequest $request,
        Campaign $campaign,
        RecordConversionAction $action,
    ): JsonResponse {
        $conversion = $action->execute(
            $campaign,
            $request->validated('external_id'),
            $request->validated('source'),
        );

        return response()->json([
            'data' => [
                'conversion' => new ConversionResource($conversion),
            ],
        ], 201);
    }
}
```

### 6.3 Action design

```php
class RecordConversionAction
{
    public function execute(
        Campaign $campaign,
        string $externalId,
        ?string $source = null,
    ): Conversion {
        $revenue = $campaign->offer->payout;

        try {
            return $campaign->conversions()->create([
                'external_id' => $externalId,
                'source' => $source,
                'revenue' => $revenue,
                'status' => ConversionStatus::Pending,
                'converted_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new DuplicateConversionException($externalId, $e);
        }
    }
}
```

### 6.4 Form Request design

```php
class StoreConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        if (! $campaign instanceof Campaign) {
            return false;
        }

        return $this->user()?->can(
            'recordConversion',
            $campaign,
        ) === true;
    }

    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

No `after()` hook — Campaign status is not checked. No `unique` validation rule on `external_id` — uniqueness is enforced at the database level only.

### 6.5 Policy design

```php
// Added to existing CampaignPolicy
public function recordConversion(User $user, Campaign $campaign): bool
{
    return $this->ownsCampaign($user, $campaign);
}
```

Uses the existing `ownsCampaign` private method: `$user->id === $campaign->offer->user_id`.

### 6.6 Exception design

```php
class DuplicateConversionException extends RuntimeException
{
    public function __construct(
        string $externalId,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "A conversion with external ID \"{$externalId}\" already exists.",
            0,
            $previous,
        );
    }
}
```

Registered in `bootstrap/app.php` following the `InvalidCampaignTransition` pattern:

```php
$exceptions->render(
    function (
        DuplicateConversionException $exception,
        Request $request,
    ): ?JsonResponse {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'message' => 'A conversion with this external ID already exists.',
            'errors' => [
                'external_id' => [
                    $exception->getMessage(),
                ],
            ],
        ], 409);
    },
);
```

## 7. Concurrency and Database Behavior

### 7.1 Deterministic duplicate handling

The database UNIQUE constraint on `external_id` is the final protection. Two concurrent requests attempting to insert the same `external_id` will result in exactly one successful INSERT and one UNIQUE constraint violation.

### 7.2 Exception class

`Illuminate\Database\UniqueConstraintViolationException` is the exact Laravel exception thrown by the database layer. This is the same exception caught by `GenerateTrackingLinkAction`.

### 7.3 Catch scope

The Action catches only `UniqueConstraintViolationException`. Unrelated database exceptions (connection failures, constraint violations on other columns) are NOT caught and propagate normally.

### 7.4 Response behavior

| Scenario | Response |
|---|---|
| First valid conversion | `201 Created` with ConversionResource |
| Duplicate `external_id` | `409 Conflict` with error message |
| Two concurrent duplicates | One `201`, one `409` — database guarantees exactly one row |
| Unknown Campaign | `404 Not Found` |
| Invalid payload | `422 Validation Error` |
| Foreign Campaign | `403 Forbidden` |
| Guest request | `401 Unauthorized` |

## 8. Eloquent Relationships

### 8.1 `Campaign::conversions()`

```php
public function conversions(): HasMany
{
    return $this->hasMany(Conversion::class);
}
```

### 8.2 `Conversion::campaign()`

```php
public function campaign(): BelongsTo
{
    return $this->belongsTo(Campaign::class);
}
```

### 8.3 Cascade behavior

- Deleting a `Campaign` cascades to its `Conversion` records via `ON DELETE CASCADE` on `conversions.campaign_id`.

## 9. Revenue Snapshotting

### 9.1 Server-derived revenue

The `revenue` field is snapshotted from `Offer.payout` at the time of conversion recording:

```php
$revenue = $campaign->offer->payout;
```

### 9.2 Why snapshotting

- The Offer payout may change after a conversion is recorded.
- Historical conversions must retain the revenue value at the time of conversion.
- Financial reporting depends on consistent revenue values.

### 9.3 Client cannot submit revenue

The `StoreConversionRequest` does not accept `revenue` in its `rules()`. The Action derives it from the server-side Offer relationship. This prevents amount tampering.

### 9.4 Why `revenue` not `payout`

The MCD/MLD define the conversion field as `revenue`. The `payout` is the Offer's attribute — the source value. When snapshotted onto the Conversion, it becomes `revenue` (what was earned for that specific conversion). This aligns with the documented schema and prepares for KAN-18 statistics without requiring a naming correction.

## 10. Security

### 10.1 Ownership isolation

`CampaignPolicy::recordConversion` verifies the authenticated user owns the Campaign through the chain: `Campaign → Offer → User`. Foreign Campaigns return `403`.

### 10.2 Mass assignment

`Conversion::$fillable` includes only the fields the Action sets. The client cannot inject `campaign_id`, `offer_id`, `user_id`, or any server-derived field through request body.

### 10.3 Enumeration risk

The nested route exposes the Campaign ID. However, this is consistent with existing endpoints (`POST /api/v1/campaigns/{campaign}/tracking-links`). The policy prevents unauthorized access.

### 10.4 Financial value protection

`revenue` is server-derived from `Offer.payout`. The client cannot submit a custom revenue value.

### 10.5 Error message leakage

The `DuplicateConversionException` message includes the `external_id` value. This is the client-submitted value, not internal state. No database details, stack traces, or internal paths are exposed.

## 11. Postman/Newman Collection

An import-ready Collection v2.1 file will be created covering:

| Test | Method | Expected |
|---|---|---|
| Health check | GET `/api/v1/health` | `200` |
| Register owner | POST `/api/v1/auth/register` | `201` |
| Login owner | POST `/api/v1/auth/login` | `200` |
| Create offer | POST `/api/v1/offers` | `201` |
| Create campaign | POST `/api/v1/campaigns` | `201` |
| Record conversion | POST `/api/v1/campaigns/{id}/conversions` | `201` |
| Record duplicate conversion | POST `/api/v1/campaigns/{id}/conversions` | `409` |
| Record different conversion | POST `/api/v1/campaigns/{id}/conversions` | `201` |
| Unknown campaign | POST `/api/v1/campaigns/99999/conversions` | `404` |

KAN-14 and KAN-15 are regression-tested separately.

## 12. Planned Production Files

Create after approval:

- `database/migrations/..._create_conversions_table.php`
- `app/Models/Conversion.php`
- `database/factories/ConversionFactory.php`
- `app/Actions/Conversion/RecordConversionAction.php`
- `app/Exceptions/DuplicateConversionException.php`
- `app/Http/Controllers/Api/V1/ConversionController.php`
- `app/Http/Requests/Api/V1/Conversion/StoreConversionRequest.php`
- `app/Http/Resources/Api/V1/ConversionResource.php`
- `tests/Feature/Api/V1/ConversionApiTest.php`
- `postman/CPAFlow-AI-KAN-16.postman_collection.json`

Modify after approval:

- `app/Models/Campaign.php` — add `conversions()` relationship
- `app/Policies/CampaignPolicy.php` — add `recordConversion()` method
- `routes/api.php` — add conversion route
- `bootstrap/app.php` — register `DuplicateConversionException` handler
- `docs/conception-technique.md` — update MLD, add implementation status
