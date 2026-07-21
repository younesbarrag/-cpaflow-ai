# Design: Create and List CPA Offers (KAN-11)

## 1. Current-State Analysis

### What KAN-8/9/10 Already Provides

| Component | File | KAN-11 Relevance |
|-----------|------|-------------------|
| User model | `app/Models/User.php` | `HasApiTokens`, `#[Fillable]`, will receive `offers()` relationship |
| OfferStatus enum | `app/Enums/OfferStatus.php` | Draft, Active, Suspended, Archived — ready for use |
| AuthController | `app/Http/Controllers/Api/V1/AuthController.php` | Pattern reference for thin controllers |
| ProfileController (API) | `app/Http/Controllers/Api/V1/ProfileController.php` | Pattern reference: delegates to Action |
| UserResource | `app/Http/Resources/Api/V1/UserResource.php` | Pattern reference for OfferResource |
| RegisterUserAction | `app/Actions/Auth/RegisterUserAction.php` | Pattern reference: explicit typed parameters |
| UpdateUserProfileAction | `app/Actions/Profile/UpdateUserProfileAction.php` | Pattern reference: Action receives User + fields |
| Form Requests | `app/Http/Requests/Api/V1/Auth/` | Pattern reference: `authorize()`, `rules()`, `prepareForValidation()` |
| Routes | `routes/api.php` | `auth:sanctum` group, versioning pattern |
| Bootstrap middleware | `bootstrap/app.php` | Admin alias registered |
| MLD offers table | `docs/conception-technique.md` | Planned schema reference |
| Tests | `tests/Feature/Api/V1/` | Pest + RefreshDatabase pattern |

### What Is Missing

| Component | Purpose |
|-----------|---------|
| `offers` database table | Stores offer data with FK to users |
| `Offer` model | Eloquent model with casts and relationships |
| `User::offers()` | HasMany relationship |
| `CreateOfferAction` | Business logic for offer creation |
| `StoreOfferRequest` | Validation for offer creation |
| `OfferController` | HTTP layer for create and list |
| `OfferResource` | JSON response structure |
| `OfferFactory` | Test data generation |
| Offer API tests | Verification of behavior |

## 2. Database Schema

### Final `offers` Table

```sql
CREATE TABLE offers (
    id              BIGINT UNSIGNED   PK AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED   NOT NULL,
    name            VARCHAR(255)      NOT NULL,
    destination_url VARCHAR(2048)     NOT NULL,
    payout          DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    status          VARCHAR(20)       NOT NULL DEFAULT 'draft',
    description     TEXT              NULL,
    created_at      TIMESTAMP         NULL,
    updated_at      TIMESTAMP         NULL,

    CONSTRAINT fk_offers_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_offers_user_status (user_id, status)
);
```

### Index Strategy

| Index | Columns | Justification |
|-------|---------|---------------|
| `fk_offers_user_id` (FK) | `user_id` | Foreign key constraint. In MySQL/InnoDB, the FK automatically creates an index on `user_id`. In SQLite, the FK also creates an implicit index. |
| `idx_offers_user_status` (composite) | `user_id`, `status` | Composite index covering the primary query pattern (list offers for a user, potentially filtered by status). The leading `user_id` column supports `WHERE user_id = ?` queries. The composite structure enables future status filtering without a separate index. |

**Not added:**
- Standalone `user_id` index — redundant because the FK constraint already creates one.
- Standalone `status` index — premature until a concrete query pattern requires it.
- `created_at` index — not justified until sorting/filtering by date becomes a query pattern.

### MySQL and SQLite Compatibility

- `VARCHAR(2048)` — supported by both MySQL and SQLite (SQLite treats VARCHAR as TEXT internally).
- `DECIMAL(12,2)` — supported by both MySQL and SQLite (SQLite stores as TEXT, but Laravel's `decimal` cast handles serialization correctly).
- `BIGINT UNSIGNED` — supported by both.
- `ON DELETE CASCADE` — supported by both.
- `DEFAULT 'draft'` — supported by both.

### Foreign-Key Delete Behavior

**Selected strategy: `ON DELETE CASCADE`**

**Reason:** The current Breeze profile flow supports account deletion (`ProfileController::destroy()`). When a user is deleted, their offers should be deleted too — there is no business reason to keep orphaned offers. CASCADE ensures database-level integrity without requiring application-level pre-deletion of offers.

**Effect on account deletion:** When `DELETE FROM users WHERE id = ?` executes, MySQL/SQLite automatically deletes all offers with matching `user_id`. The Breeze `destroy()` flow does not need modification.

**Why not RESTRICT:** RESTRICT would block user deletion while offers exist, requiring the user to manually delete all offers first. This creates a poor UX and is unnecessary for the CPAFlow business model (offers are user-owned data, not shared entities).

**Tests required:** Verify that deleting a user also deletes their offers (covered by model relationship tests).

### Default Status Strategy

The migration sets `DEFAULT 'draft'` on the `status` column. This provides a database-level safety net. The `StoreOfferRequest` also validates status via `Rule::enum(OfferStatus::class)`, so application-level validation ensures only valid enum values are accepted.

If the client submits `status: "active"`, the offer is created as active. If no status is submitted, the database default `draft` applies. This is intentional — it allows users to create drafts without explicitly specifying status.

### Decimal Precision

`DECIMAL(12,2)` provides:
- 10 integer digits + 2 decimal digits: max value 9,999,999,999.99
- 2 decimal digits: cents precision
- Total capacity: $9,999,999,999.99

This is more than sufficient for CPA offer payouts (typically $0.01 to $500.00). The extra integer digits accommodate future currency needs without migration.

**Validation maximum:** `max:9999999999.99` — matches the database column capacity exactly. Negative values are rejected by `min:0`.

## 3. Offer Archival Strategy

**Decision: Status-based archival only. No `SoftDeletes` in KAN-11.**

**Rationale:**
- The `OfferStatus` enum already includes `Archived = 'archived'`.
- KAN-12 will implement archival by updating the status column.
- Adding `SoftDeletes` (a `deleted_at` column) introduces a second deletion mechanism that conflicts with status-based archival.
- Keeping the schema simple in KAN-11 avoids unnecessary migration complexity.
- If soft-deletion is needed later, it can be added in a dedicated migration with clear justification.

**KAN-12 can build on this by:**
- Updating `status` to `OfferStatus::Archived` via an `archive()` method on the model.
- Filtering out archived offers from active queries using a scope.

**No migration change required for KAN-12 archival.**

## 4. Model Design

### `App\Models\Offer`

```php
namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'destination_url',
        'payout',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'payout' => 'decimal:2',
            'status' => OfferStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Key decisions:**

| Decision | Rationale |
|----------|-----------|
| `#[Fillable]` or `$fillable` property | Use `$fillable` property (not `#[Fillable]` attribute) — consistent with how the existing codebase handles mass assignment; `user_id` is excluded |
| `payout` cast: `decimal:2` | Returns a precise string representation, never a float. Prevents floating-point corruption (e.g., `0.1 + 0.2 !== 0.3`). Laravel's `decimal` cast serializes to string. |
| `status` cast: `OfferStatus::class` | Type-safe enum comparison; prevents invalid status values at the model level |
| No `user_id` in `$fillable` | Ownership is set through the relationship (`$user->offers()->create(...)` or `$offer->user()->associate($user)`), never from public input |
| No model events | No `boot()` or `saving` callbacks needed — keep the model simple |
| `BelongsTo` relationship | Standard inverse of `HasMany`; allows eager loading via `with('user')` if needed later |

### User Model Extension

Add to `App\Models\User`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function offers(): HasMany
{
    return $this->hasMany(Offer::class);
}
```

This enables:
- `$user->offers` — collection of user's offers
- `$user->offers()->create(...)` — creates offer with `user_id` automatically set
- `$user->offers()->where(...)` — scoped queries
- `$user->offers()->paginate(...)` — paginated queries

## 5. API Routes

### New Routes

```
POST /api/v1/offers    → OfferController::store   [auth:sanctum]  name: api.v1.offers.store
GET  /api/v1/offers    → OfferController::index   [auth:sanctum]  name: api.v1.offers.index
```

**Both routes are inside the `auth:sanctum` middleware group.**

### Route Placement

```php
Route::middleware('auth:sanctum')->group(function () {
    // ... existing routes ...

    Route::get('/offers', [OfferController::class, 'index'])
        ->name('api.v1.offers.index');

    Route::post('/offers', [OfferController::class, 'store'])
        ->name('api.v1.offers.store');
});
```

### Single-Offer Endpoint Decision

**KAN-11 does NOT create `GET /api/v1/offers/{offer}`.**

**Rationale:**
- The Jira acceptance criteria only require creation and paginated listing.
- A detail endpoint requires additional authorization logic (is this the user's offer?) — this belongs in KAN-12 when update/archive/access patterns are designed.
- Adding it now would introduce an incomplete feature (no update, no delete) without clear value.

### Final `routes/api.php` After KAN-11

```php
Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::post('/auth/login', ...)->name('api.v1.auth.login');
    Route::post('/auth/register', ...)->middleware('throttle:api-register')->name('api.v1.auth.register');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', ...)->name('api.v1.auth.logout');
        Route::get('/auth/user', ...)->name('api.v1.auth.user');
        Route::patch('/profile', ...)->name('api.v1.profile.update');

        // KAN-11: Offers
        Route::get('/offers', [OfferController::class, 'index'])->name('api.v1.offers.index');
        Route::post('/offers', [OfferController::class, 'store'])->name('api.v1.offers.store');
    });
});
```

## 6. Validation

### `App\Http\Requests\Api\V1\Offer\StoreOfferRequest`

```php
public function authorize(): bool
{
    return true; // auth:sanctum handles authentication
}

public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'destination_url' => ['required', 'string', 'url:http,https', 'max:2048'],
        'payout' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        'status' => ['required', Rule::enum(OfferStatus::class)],
        'description' => ['nullable', 'string', 'max:10000'],
    ];
}

public function prepareForValidation(): void
{
    if ($this->has('name')) {
        $this->merge(['name' => trim($this->name)]);
    }

    if ($this->has('destination_url')) {
        $this->merge(['destination_url' => trim($this->destination_url)]);
    }

    if ($this->has('description') && $this->description !== null) {
        $this->merge(['description' => trim($this->description)]);
    }
}
```

### Validation Rules Evaluation

| Field | Rules | Rationale |
|-------|-------|-----------|
| `name` | `required`, `string`, `max:255` | Offer name is mandatory; standard string length |
| `destination_url` | `required`, `string`, `url:http,https`, `max:2048` | Must be a valid URL; `url:http,https` rule accepts only HTTP and HTTPS protocols; `max:2048` matches column type; `active_url` rejected (performs unreliable DNS checks) |
| `payout` | `required`, `numeric`, `min:0`, `max:9999999999.99`, `decimal:0,2` | Monetary amount; must be numeric, non-negative, at most 2 decimal places; `max:9999999999.99` matches DECIMAL(12,2) column capacity; `decimal:0,2` rejects values like `10.999` |
| `status` | `required`, `Rule::enum(OfferStatus::class)` | Must be a valid OfferStatus value; enum validation prevents invalid strings; `required` forces explicit status (no hidden default at validation layer) |
| `description` | `nullable`, `string`, `max:10000` | Optional; reasonable max length prevents abuse |

### Payout Precision Strategy

- **Validation:** `decimal:0,2` ensures at most 2 decimal places at the input layer; `max:9999999999.99` prevents values exceeding the DECIMAL(12,2) column capacity.
- **Storage:** `DECIMAL(12,2)` in MySQL stores exact values.
- **Model cast:** `decimal:2` returns a string (e.g., `"25.50"`), never a float.
- **API response:** Serialized as a string in JSON (e.g., `"payout": "25.50"`).
- **Never cast to float:** Prevents `0.1 + 0.2 = 0.30000000000000004` corruption.

### `prepareForValidation`

Normalize input before validation:
- Trim surrounding whitespace from `name`;
- Trim surrounding whitespace from `destination_url`;
- Trim surrounding whitespace from `description` (if not null);
- Convert empty `description` to null.

This ensures that `"  Fitness Trial  "` becomes `"Fitness Trial"` and prevents whitespace-only values from passing validation.

## 7. Ownership Strategy

### Creation

The offer is always created through the authenticated user's relationship:

```php
$offer = $user->offers()->create([
    'name' => $name,
    'destination_url' => $destinationUrl,
    'payout' => $payout,
    'status' => $status,
    'description' => $description,
]);
```

or equivalently through the Action:

```php
public function execute(
    User $user,
    string $name,
    string $destinationUrl,
    string $payout,
    OfferStatus $status,
    ?string $description,
): Offer {
    return $user->offers()->create([
        'name' => $name,
        'destination_url' => $destinationUrl,
        'payout' => $payout,
        'status' => $status,
        'description' => $description,
    ]);
}
```

### Why This Is Safe

| Protection Layer | Mechanism |
|------------------|-----------|
| `#[Fillable]` / `$fillable` | `user_id` is NOT in the fillable list — mass assignment silently ignores it |
| Relationship creation | `$user->offers()->create(...)` automatically sets `user_id` to `$user->id` |
| Form Request | Does NOT validate or accept `user_id` |
| Action | Receives `User $user` from authenticated context, not from input |

### Ownership Test Requirement

A test must prove that submitting `user_id` with a different user's ID does NOT create an offer for that user. The submitted `user_id` is silently ignored.

## 8. CreateOfferAction

### `App\Actions\Offer\CreateOfferAction`

```php
namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;

class CreateOfferAction
{
    public function execute(
        User $user,
        string $name,
        string $destinationUrl,
        string $payout,
        OfferStatus $status,
        ?string $description,
    ): Offer {
        return $user->offers()->create([
            'name' => $name,
            'destination_url' => $destinationUrl,
            'payout' => $payout,
            'status' => $status,
            'description' => $description,
        ]);
    }
}
```

**Responsibilities:**
- Receive authenticated `User` instance
- Receive explicit typed parameters for each business field
- Create and associate the Offer with the User via the relationship
- Return the created `Offer`

**Must NOT:**
- Return HTTP responses
- Perform request validation
- Authorize arbitrary users
- Accept `user_id` from public input
- Accept arbitrary arrays
- Paginate results
- Handle unrelated filtering

## 9. OfferController

### `App\Http\Controllers\Api\V1\OfferController`

```php
namespace App\Http\Controllers\Api\V1;

use App\Actions\Offer\CreateOfferAction;
use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Offer\StoreOfferRequest;
use App\Http\Resources\Api\V1\OfferResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function store(StoreOfferRequest $request, CreateOfferAction $action): JsonResponse
    {
        $offer = $action->execute(
            $request->user(),
            $request->validated('name'),
            $request->validated('destination_url'),
            $request->validated('payout'),
            OfferStatus::from($request->validated('status')),
            $request->validated('description'),
        );

        return response()->json([
            'data' => [
                'offer' => new OfferResource($offer),
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $offers = $request->user()
            ->offers()
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => OfferResource::collection($offers),
            'links' => $offers->links(null, 'array', JSON_UNESCAPED_SLASHES),
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }
}
```

### Controller Responsibilities

| Method | Responsibility |
|--------|----------------|
| `store()` | Receives FormRequest, delegates to Action, returns HTTP 201 + OfferResource |
| `index()` | Queries authenticated user's offers, orders by newest first, paginates, returns OfferResource collection |

### Listing Strategy

- **Query scope:** `$request->user()->offers()` — automatically scopes to the authenticated user.
- **Ordering:** `orderByDesc('id')` — deterministic, newest first; `id` as tie-breaker for equal `created_at`.
- **Pagination:** Default 15 items per page. `paginate()` returns `LengthAwarePaginator` with `data`, `links`, and `meta`.
- **No separate Action for listing:** The query is simple enough to live in the controller. Adding a `ListUserOffersAction` would be over-engineering for a one-line query scope.

### Why No `ListUserOffersAction`

The listing query is `$request->user()->offers()->orderByDesc('id')->paginate(15)`. This is a single Eloquent chain that reads clearly in the controller. Extracting it to a separate Action class adds indirection without value. If the listing logic grows complex (filters, date ranges, aggregations), it can be extracted then.

## 10. Pagination Strategy

| Parameter | Value | Rationale |
|-----------|-------|-----------|
| Default page size | 15 | Matches Laravel's default; reasonable for initial load |
| Maximum page size | Not configurable in KAN-11 | Avoids unnecessary complexity; can be added later |
| `per_page` parameter | Not accepted in KAN-11 | Keeps the API simple; configurable pagination can be added when the frontend needs it |
| Ordering | `ORDER BY id DESC` | Deterministic; newest first; `id` prevents ties |

### Pagination Response Shape

```json
{
  "data": [ ... ],
  "links": {
    "first": "http://localhost/api/v1/offers?page=1",
    "last": "http://localhost/api/v1/offers?page=5",
    "prev": null,
    "next": "http://localhost/api/v1/offers?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

## 11. OfferResource

### `App\Http\Resources\Api\V1\OfferResource`

```php
namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'destination_url' => $this->destination_url,
            'payout' => $this->payout,
            'status' => $this->status->value,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### Fields

| Field | Type | Rationale |
|-------|------|-----------|
| `id` | integer | Unique identifier |
| `name` | string | Offer name |
| `destination_url` | string | Landing page URL |
| `payout` | string (decimal) | Serialized via `decimal:2` cast — never a float |
| `status` | string | Serialized via `OfferStatus::value` — string enum value |
| `description` | string or null | Optional description |
| `created_at` | datetime or null | Standard timestamp |
| `updated_at` | datetime or null | Standard timestamp |

### Excluded Fields

| Field | Reason |
|-------|--------|
| `user_id` | Authenticated user already knows their own ID; exposing it adds no value and leaks an internal identifier |
| `password` | Not on the offers table |
| `remember_token` | Not on the offers table |

## 12. API Contracts

### Create Offer

```
POST /api/v1/offers
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Fitness Trial",
  "destination_url": "https://example.com/offer",
  "payout": "25.50",
  "status": "draft",
  "description": "Optional description"
}
```

**Success — HTTP 201:**

```json
{
  "data": {
    "offer": {
      "id": 1,
      "name": "Fitness Trial",
      "destination_url": "https://example.com/offer",
      "payout": "25.50",
      "status": "draft",
      "description": "Optional description",
      "created_at": "2026-07-20T16:00:00.000000Z",
      "updated_at": "2026-07-20T16:00:00.000000Z"
    }
  }
}
```

**Validation failure — HTTP 422:**

```json
{
  "message": "The provided data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "payout": ["The payout must be greater than or equal to 0."]
  }
}
```

**Unauthenticated — HTTP 401:**

```json
{
  "message": "Unauthenticated."
}
```

### List Offers

```
GET /api/v1/offers
Authorization: Bearer {token}
```

**Success — HTTP 200:**

```json
{
  "data": [
    {
      "id": 2,
      "name": "VPN Free Trial",
      "destination_url": "https://example.com/vpn",
      "payout": "3.50",
      "status": "active",
      "description": null,
      "created_at": "2026-07-20T15:30:00.000000Z",
      "updated_at": "2026-07-20T15:30:00.000000Z"
    },
    {
      "id": 1,
      "name": "Fitness Trial",
      "destination_url": "https://example.com/offer",
      "payout": "25.50",
      "status": "draft",
      "description": "Optional description",
      "created_at": "2026-07-20T16:00:00.000000Z",
      "updated_at": "2026-07-20T16:00:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost/api/v1/offers?page=1",
    "last": "http://localhost/api/v1/offers?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 2
  }
}
```

**Empty list — HTTP 200:**

```json
{
  "data": [],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

## 13. Policy Decision

**OfferPolicy is NOT created in KAN-11.**

**Rationale:**
- KAN-11 operations (create, list) are inherently scoped to the authenticated user via `$request->user()->offers()` and `$user->offers()->create(...)`.
- There is no cross-user authorization scenario in KAN-11.
- An OfferPolicy would be empty or trivial in KAN-11 (all checks would be `return $user->id === $offer->user_id`).
- KAN-12 introduces update and archive operations where per-offer authorization becomes meaningful. The Policy should be created then with the full set of methods (`viewAny`, `view`, `update`, `archive`, `delete`).

**Documented decision:** Creating an empty or artificial Policy in KAN-11 violates the project convention of "one class per responsibility." The Policy is deferred to KAN-12 when it has real authorization logic.

## 14. Factory

### `database/factories/OfferFactory.php`

```php
namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'destination_url' => fake()->url(),
            'payout' => fake()->randomFloat(2, 0, 500),
            'status' => fake()->randomElement(OfferStatus::cases()),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => OfferStatus::Draft]);
    }

    public function active(): static
    {
        return $this->state(['status' => OfferStatus::Active]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
```

**Key decisions:**
- Creates a related `User` by default (via `User::factory()`).
- `fake()->url()` generates valid HTTP/HTTPS URLs.
- `fake()->randomFloat(2, 0, 500)` generates values compatible with `DECIMAL(12,2)`.
- `fake()->randomElement(OfferStatus::cases())` ensures valid enum values.
- `description` is optional (60% chance of being set).
- State methods for common scenarios: `draft()`, `active()`, `forUser()`.

## 15. Migration Execution Strategy

**Implementation sequence:**

1. Create the migration file.
2. Implement `Offer` model, relationships, and `OfferFactory`.
3. Run tests using SQLite in-memory (no MySQL needed for tests).
4. Review the generated schema and constraints.
5. Check MySQL migration status.
6. Run only the pending additive migration: `php artisan migrate`.
7. Recheck migration status.

**Rules:**
- Never run `migrate:fresh`, `migrate:reset`, `migrate:rollback`, or `db:wipe`.
- Do not modify existing executed migrations.
- The migration is purely additive (new table only).

## 16. Documentation Update

After implementation, update `docs/conception-technique.md`:

### Changes

| Section | Change |
|---------|--------|
| MLD `offers` table | Update `Planifié` → `Implémenté`; update `destination_url` from `TEXT` to `VARCHAR(2048)`; update `payout` from `DECIMAL(10,2)` to `DECIMAL(12,2)` |
| User → Offer relationship | Mark as implemented |
| OfferStatus usage | Mark as used (cast on Offer.status) |
| Project current-state | Add offers table, model, API endpoints |
| Routes list | Add `GET /api/v1/offers` and `POST /api/v1/offers` |

### What NOT to update

- Do not mark campaigns, tracking, clicks, conversions, expenses, dashboard, or AI as implemented.
- Do not update `DECIMAL` precision on other planned tables (conversions, campaign_expenses) — that is a separate decision.

## 17. Security Summary

| Concern | Implementation |
|---------|----------------|
| API auth | `auth:sanctum` on all offer routes |
| Ownership creation | `$request->user()->offers()->create(...)` — user_id set automatically |
| Ownership listing | `$request->user()->offers()->paginate(...)` — scoped to authenticated user |
| Mass-assignment | `user_id` NOT in `$fillable` |
| Input validation | `StoreOfferRequest` validates all fields |
| URL validation | `url` rule accepts only HTTP/HTTPS |
| Payout precision | `decimal:0,2` validation + `decimal:2` model cast |
| Status integrity | `Rule::enum(OfferStatus::class)` validation + enum model cast |
| No cross-user access | Listing scoped through relationship; no user_id parameter accepted |
| No secrets in response | OfferResource exposes only offer data |
| No raw SQL | All queries use Eloquent |
| Test isolation | SQLite in-memory; cannot modify MySQL |

## 18. Alternatives Considered and Rejected

### 1. Using `array $data` with Explicit Field Assignment in Action

**Rejected because:** `$user->offers()->create($data)` is simpler, equally safe (Form Request already constrains the data), and follows the pattern established by `RegisterUserAction`.

### 2. Creating a `ListUserOffersAction`

**Rejected because:** The listing query is a single Eloquent chain. Extracting it adds indirection without value. Can be extracted later if complexity grows.

### 3. Adding `GET /api/v1/offers/{offer}` in KAN-11

**Rejected because:** The Jira acceptance criteria only require creation and listing. A detail endpoint requires per-offer authorization (is this the user's offer?) which belongs in KAN-12.

### 4. Using `SoftDeletes` on offers

**Rejected because:** Status-based archival via `OfferStatus::Archived` is simpler and sufficient. `SoftDeletes` introduces a second deletion mechanism that conflicts with status-based lifecycle. Can be added later if needed.

### 5. Creating an `OfferPolicy` in KAN-11

**Rejected because:** All KAN-11 operations are inherently scoped to the authenticated user via relationships. A Policy would be empty. Deferred to KAN-12 when per-offer authorization is needed.

### 6. Accepting `per_page` query parameter

**Rejected because:** Adds validation complexity without clear value in KAN-11. The default page size of 15 is sufficient. Configurable pagination can be added when the frontend needs it.

### 7. Using `created_at` for ordering

**Rejected because:** Two offers created in the same second would have equal `created_at`, making pagination unpredictable. Using `id DESC` is deterministic and sufficient.

### 8. Making `status` optional (database default only)

**Rejected because:** `status` is required in the Form Request. This forces the client to explicitly choose a status, making the API contract clear. The database default is a safety net, not the primary mechanism.

## 19. File Inventory

### Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/..._create_offers_table.php` | Offers table migration |
| `app/Models/Offer.php` | Offer Eloquent model |
| `app/Actions/Offer/CreateOfferAction.php` | Offer creation business logic |
| `app/Http/Controllers/Api/V1/OfferController.php` | API controller (store, index) |
| `app/Http/Requests/Api/V1/Offer/StoreOfferRequest.php` | Creation validation |
| `app/Http/Resources/Api/V1/OfferResource.php` | JSON response structure |
| `database/factories/OfferFactory.php` | Test data generation |
| `tests/Feature/Api/V1/OfferApiTest.php` | API behavior tests |

### Files to Modify

| File | Change |
|------|--------|
| `app/Models/User.php` | Add `offers(): HasMany` relationship + import |
| `routes/api.php` | Add `GET /offers` and `POST /offers` routes + import |
| `docs/conception-technique.md` | Mark offers as implemented |

### Files Reused (no changes)

| File | Role |
|------|------|
| `app/Enums/OfferStatus.php` | Draft, Active, Suspended, Archived |
| `app/Http/Resources/Api/V1/UserResource.php` | Pattern reference |
| `app/Actions/Auth/RegisterUserAction.php` | Pattern reference |
| `app/Http/Controllers/Api/V1/AuthController.php` | Pattern reference |
| `tests/Feature/Api/V1/AuthApiTest.php` | Regression verification |
| `tests/Feature/Api/V1/HealthTest.php` | Regression verification |
| `tests/Feature/Middleware/AdminMiddlewareTest.php` | Regression verification |
| `tests/Feature/Api/V1/ProfileApiTest.php` | Regression verification |
