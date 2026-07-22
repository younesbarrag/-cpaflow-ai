# Design — KAN-12: Modifier, filtrer et archiver ses offres CPA

## Architecture Decisions

### 1. Authorization Strategy — OfferPolicy

**Decision:** Create `App\Policies\OfferPolicy` with `update` and `archive` methods.

**Rationale:**
- Laravel 13 auto-discovers policies in `App\Policies` — no explicit registration needed
- Policy methods receive `User $user` and `Offer $offer`
- Ownership rule: `$user->id === $offer->user_id`
- Controller calls `$this->authorize('update', $offer)` or `$this->authorize('archive', $offer)` before action execution

**Methods:**

```php
public function update(User $user, Offer $offer): bool
{
    return $user->id === $offer->user_id;
}

public function archive(User $user, Offer $offer): bool
{
    return $user->id === $offer->user_id;
}
```

**`view()` method:** Not required. The listing endpoint already scopes via `$request->user()->offers()`. Individual offer detail is not in scope.

### 2. Foreign-User 403 Guarantee

**Problem:** Laravel route model binding returns HTTP 404 when a model is not found. If a user accesses `/api/v1/offers/999` and offer 999 belongs to another user, the policy must return 403, not let the model binding fail.

**Solution:** Use `$this->authorize()` in the controller **after** route model binding resolves the offer. Since the offer exists (belongs to another user), route model binding succeeds. The policy then denies access, returning 403.

**Flow:**

```
Request: PATCH /api/v1/offers/999
  → Route model binding resolves Offer #999 (exists, belongs to User #2)
  → Controller: $this->authorize('update', $offer)
  → OfferPolicy::update(User #1, Offer #999) returns false
  → Laravel throws AuthorizationException → HTTP 403
```

This guarantees 403 for existing offers belonging to other users, not 404.

### 3. Archive Strategy — Status-Based

**Decision:** Archival sets `status` to `OfferStatus::Archived`. No SoftDeletes, no `deleted_at` column.

**Rationale (confirmed from KAN-11):**
- KAN-11 README explicitly states: "Status-based archival (no SoftDeletes)"
- `OfferStatus::Archived` enum value already exists
- Composite index `(user_id, status)` supports filtered queries efficiently
- Archived offers remain queryable through filters

**Idempotency:**
- Archiving an already-archived offer returns HTTP 200 with the current offer data
- No error, no special message — the operation is idempotent
- The action checks current status before updating; if already archived, it returns the offer without database write

### 4. Update Route

**Route:** `PATCH /api/v1/offers/{offer}`

**Name:** `api.v1.offers.update`

**Middleware:** `auth:sanctum`

**Behavior:**
- Route model binding resolves the offer
- `OfferPolicy::update` authorizes ownership
- `UpdateOfferRequest` validates submitted fields
- `UpdateOfferAction` applies allowed fields
- Returns `OfferResource` with HTTP 200

**Updatable Fields (all optional/partial):**

| Field | Validation | Normalization |
|-------|------------|---------------|
| `name` | `sometimes\|string\|max:255` | `trim()` |
| `destination_url` | `sometimes\|string\|url:http,https\|max:2048` | `trim()` |
| `payout` | `sometimes\|numeric\|min:0\|max:9999999999.99\|decimal:0,2` | — |
| `status` | `sometimes\|Rule::enum(OfferStatus::class)` | — |
| `description` | `nullable\|string\|max:10000` | `trim()` if non-null |

**Protected Fields (never updatable):**
- `user_id` — ownership must never change
- `owner_id` — mass-assignment protection
- `affiliate_id` — mass-assignment protection

**Partial Update Semantics:** All fields use `sometimes` rule. Only submitted fields are validated and updated. Omitted fields remain unchanged.

### 5. Archive Route

**Route:** `POST /api/v1/offers/{offer}/archive`

**Name:** `api.v1.offers.archive`

**Middleware:** `auth:sanctum`

**Justification for POST over PATCH:**
- Archival is a state transition action, not a partial resource update
- POST is semantically correct for actions that trigger side effects
- Consistent with common API patterns (e.g., GitHub's archive endpoints use POST)
- The endpoint performs a single action — set status to archived

**Behavior:**
- Route model binding resolves the offer
- `OfferPolicy::archive` authorizes ownership
- `ArchiveOfferAction` sets `status = OfferStatus::Archived`
- Returns `OfferResource` with HTTP 200

**Idempotency Details:**
- If offer is already archived: return HTTP 200 with current data (no database write)
- If offer is in any other status: set to archived, return HTTP 200

### 6. Filtering and Search

**Endpoint:** `GET /api/v1/offers` (extended)

**Query Parameters:**

| Parameter | Type | Validation | Description |
|-----------|------|------------|-------------|
| `status` | string | `nullable\|Rule::enum(OfferStatus::class)` | Filter by offer status |
| `search` | string | `nullable\|string\|max:255` | Search by offer name (case-insensitive LIKE) |

**Examples:**

```
GET /api/v1/offers?status=active
GET /api/v1/offers?search=fitness
GET /api/v1/offers?status=draft&search=fitness
```

**Validation via IndexOfferRequest:**
- `status`: nullable, validated against `OfferStatus` enum
- `search`: nullable, trimmed, max 255 chars, whitespace normalized

**Eloquent Scopes on Offer Model:**

```php
public function scopeStatus(Builder $query, ?OfferStatus $status): Builder
{
    if ($status === null) {
        return $query;
    }
    return $query->where('status', $status->value);
}

public function scopeSearch(Builder $query, ?string $search): Builder
{
    if ($search === null || $search === '') {
        return $query;
    }
    return $query->where('name', 'like', '%'.$search.'%');
}
```

**Scope Application in Controller:**

```php
$offers = $request->user()
    ->offers()
    ->when($request->validated('status'), fn ($q, $status) => $q->status($status))
    ->when($request->validated('search'), fn ($q, $search) => $q->search($search))
    ->orderByDesc('id')
    ->paginate(15);
```

**Security:** The query always starts with `$request->user()->offers()`, ensuring filters never expose another user's offers.

### 7. Actions Design

**UpdateOfferAction:**

```php
class UpdateOfferAction
{
    public function execute(Offer $offer, array $fields): Offer
    {
        $allowedFields = ['name', 'destination_url', 'payout', 'status', 'description'];
        $trustedFields = array_intersect_key($fields, array_flip($allowedFields));

        if (array_key_exists('status', $trustedFields)) {
            $trustedFields['status'] = $this->resolveStatus($trustedFields['status']);
        }

        $offer->fill($trustedFields);
        $offer->save();

        return $offer->refresh();
    }
}
```

- Receives an Offer instance and validated fields array
- Internal whitelist filters to only allowed fields (defense-in-depth)
- Converts status to OfferStatus enum safely
- Uses fill() + save() (respects $fillable)
- Returns the refreshed offer
- Does NOT authorize users
- Does NOT accept ownership fields
- Does NOT return HTTP responses

**ArchiveOfferAction:**

```php
class ArchiveOfferAction
{
    public function execute(Offer $offer): Offer
    {
        if ($offer->status === OfferStatus::Archived) {
            return $offer;
        }

        $offer->status = OfferStatus::Archived;
        $offer->save();

        return $offer->refresh();
    }
}
```

- Receives an Offer instance
- Checks current status before update (idempotency)
- If already archived: returns offer without database write
- Sets status to `OfferStatus::Archived`
- Returns the refreshed offer
- Does NOT authorize users
- Does NOT return HTTP responses

### 8. Form Requests

**UpdateOfferRequest:**

- `authorize()` checks `$this->user()?->can('update', $offer)` — verifies ownership via OfferPolicy before validation
- `prepareForValidation()`: trim name, destination_url, description (empty → null)
- `rules()`: all fields `sometimes`, same validation as StoreOfferRequest where applicable
- `after()`: rejects requests containing no editable fields (returns 422)
- Ownership fields (user_id, owner_id, affiliate_id) not in rules

**IndexOfferRequest:**

- `authorize()` returns `true`
- `prepareForValidation()` trims search whitespace
- `rules()`:
  - `status`: `nullable`, `Rule::enum(OfferStatus::class)`
  - `search`: `nullable`, `string`, `max:255`

### 9. Controller Design

**OfferController methods:**

```php
public function index(IndexOfferRequest $request): JsonResponse
{
    $statusValue = $request->validated('status');
    $status = is_string($statusValue) ? OfferStatus::from($statusValue) : null;
    $search = $request->validated('search');

    $offers = $request->user()
        ->offers()
        ->status($status)
        ->search(is_string($search) ? $search : null)
        ->orderByDesc('id')
        ->paginate(15);

    // ... response
}

public function update(UpdateOfferRequest $request, Offer $offer, UpdateOfferAction $action): JsonResponse
{
    Gate::authorize('update', $offer);

    $fields = $request->safe()->only([
        'name', 'destination_url', 'payout', 'status', 'description',
    ]);

    $updatedOffer = $action->execute($offer, $fields);

    return response()->json([
        'data' => ['offer' => new OfferResource($updatedOffer)],
    ]);
}

public function archive(Offer $offer, ArchiveOfferAction $action): JsonResponse
{
    Gate::authorize('archive', $offer);

    $archivedOffer = $action->execute($offer);

    return response()->json([
        'data' => ['offer' => new OfferResource($archivedOffer)],
    ]);
}
```

Controller responsibilities:
- Receive HTTP request
- Call policy authorization
- Pass validated data to action
- Return JSON response via resource

Controller does NOT:
- Contain business logic
- Query the database directly (except via `$request->user()->offers()`)
- Handle validation (delegated to FormRequest)

**Authorization defense-in-depth:**
The `update()` method uses dual authorization: `UpdateOfferRequest::authorize()` checks the policy before validation, and `Gate::authorize()` in the controller provides a safety net. For foreign users, the FormRequest returns 403 before validation runs. For owner requests, the policy is checked twice (harmless redundancy).

### 10. Migration Decision

**No new migration required.**

KAN-11 already provides:
- `status` column (`VARCHAR(20)`, default `'draft'`)
- `OfferStatus::Archived` enum value
- `user_id/status` composite index for efficient filtered queries

No schema changes are needed for update, archive, filtering, or search.

### 11. Model Changes

**Offer.php additions:**

```php
use Illuminate\Database\Eloquent\Builder;

// Add to class body:
public function scopeStatus(Builder $query, OfferStatus $status): Builder
{
    return $query->where('status', $status);
}

public function scopeSearch(Builder $query, ?string $search): Builder
{
    if (!$search) {
        return $query;
    }
    return $query->where('name', 'like', '%' . $search . '%');
}
```

No changes to `$fillable`, `$casts`, or relationships.

### 12. Route Registration

**Additions to `routes/api.php`:**

```php
Route::patch('/offers/{offer}', [OfferController::class, 'update'])
    ->name('api.v1.offers.update');

Route::post('/offers/{offer}/archive', [OfferController::class, 'archive'])
    ->name('api.v1.offers.archive');
```

Both routes within the `auth:sanctum` middleware group.

### 13. Files Summary

**Create:**

| File | Purpose |
|------|---------|
| `app/Policies/OfferPolicy.php` | Authorization for update and archive |
| `app/Actions/Offer/UpdateOfferAction.php` | Update business logic |
| `app/Actions/Offer/ArchiveOfferAction.php` | Archive business logic |
| `app/Http/Requests/Api/V1/Offer/UpdateOfferRequest.php` | Update validation |
| `app/Http/Requests/Api/V1/Offer/IndexOfferRequest.php` | Filter/search validation |
| `tests/Feature/Api/V1/OfferManagementApiTest.php` | Comprehensive tests |

**Modify:**

| File | Change |
|------|--------|
| `app/Models/Offer.php` | Add `scopeStatus()` and `scopeSearch()` |
| `app/Http/Controllers/Api/V1/OfferController.php` | Add `update()`, `archive()`, extend `index()` |
| `routes/api.php` | Add PATCH and POST routes |
| `docs/conception-technique.md` | Update status for KAN-12 |

### 14. Postman Scenarios

| # | Scenario | Method | Endpoint | Expected |
|---|----------|--------|----------|----------|
| 1 | Owner updates offer name | PATCH | `/api/v1/offers/{id}` | 200 |
| 2 | Foreign user updates offer | PATCH | `/api/v1/offers/{id}` | 403 |
| 3 | Guest updates offer | PATCH | `/api/v1/offers/{id}` | 401 |
| 4 | Owner archives offer | POST | `/api/v1/offers/{id}/archive` | 200 |
| 5 | Foreign user archives offer | POST | `/api/v1/offers/{id}/archive` | 403 |
| 6 | Filter by status | GET | `/api/v1/offers?status=active` | 200 |
| 7 | Search by name | GET | `/api/v1/offers?search=fitness` | 200 |
| 8 | Combined filter + search | GET | `/api/v1/offers?status=draft&search=fitness` | 200 |
| 9 | Invalid status filter | GET | `/api/v1/offers?status=invalid` | 422 |
| 10 | Ownership transfer attempt | PATCH | `/api/v1/offers/{id}` with `user_id` | 200 (user_id ignored) |
