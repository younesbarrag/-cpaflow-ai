# Design - KAN-14: Generer un lien de tracking pour une campagne

## 1. Existing Conventions Inspected

The design follows these implemented conventions:

- `routes/api.php`: `/api/v1` prefix, named routes, and `auth:sanctum` grouping.
- `Campaign` and its migration: relationship-owned records, guarded ownership, enum casts.
- `CampaignPolicy`: strict ownership with no Admin bypass and Laravel policy auto-discovery.
- `UpdateCampaignRequest`: policy authorization before rule validation.
- Campaign Actions: business mutations without authorization, request objects, or HTTP responses.
- `CampaignResource`: money as an exact decimal string and dates using Carbon JSON serialization.
- `CampaignController`: thin orchestration and `Gate::authorize` before mutations.
- Campaign Pest tests: Sanctum authentication, `RefreshDatabase`, persisted-state assertions.
- `docs/conception-technique.md`: numbered French technical architecture covering MCD, MLD, enums, routes, conventions, implementation status, and open decisions.
- `InvalidCampaignTransition` domain exception mapped to `409` in `bootstrap/app.php`.

## 2. Data Model

### 2.1 `tracking_links` table

| Column | SQL/Laravel type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED`, `$table->id()` | No | auto increment | Primary key |
| `campaign_id` | `BIGINT UNSIGNED`, `$table->foreignId('campaign_id')` | No | none | FK to `campaigns.id` |
| `code` | `VARCHAR(32)`, `$table->string('code', 32)` | No | none | URL-safe, UNIQUE |
| `created_at` | timestamp | Yes | framework-managed | `$table->timestamps()` convention |
| `updated_at` | timestamp | Yes | framework-managed | `$table->timestamps()` convention |

Migration definition:

```php
$table->id();
$table->foreignId('campaign_id')
    ->constrained()
    ->cascadeOnDelete();
$table->string('code', 32)->unique();
$table->timestamps();
```

This is an additive `create_tracking_links_table` migration. No destructive command or schema operation is permitted.

Schema confirmation:
- No `user_id` — ownership is derived through `Campaign → Offer → User`.
- No `destination_url` — the canonical URL lives on `Offer.destination_url`.
- No `is_active` — KAN-14 contains no deactivation, rotation, or archival. Tracking-link usability in KAN-15 depends on the related Campaign status.
- No `deleted_at` — soft deletes are not needed.
- No `expires_at` — link expiration is a separate product decision.
- No `click_count` — click recording is outside KAN-14 scope.
- No speculative indexes beyond the foreign-key index and unique code index.

### 2.2 Direct `user_id` decision

`tracking_links.user_id` will not exist. The campaign is the link's aggregate parent and already stores ownership through the Offer chain. Duplicating `user_id` would create two ownership values that could diverge, require synchronization, and allow inconsistent rows.

The expected tracking-link volume does not justify denormalization. Future link listing (outside KAN-14) would use `TrackingLink::query()->whereHas('campaign.offer', fn ($q) => $q->where('user_id', $userId))`, which uses existing indexed ownership paths. A direct owner column should be reconsidered only with measured query evidence and a database-enforced consistency design.

### 2.3 `destination_url` decision

`destination_url` will not be stored on `tracking_links`. The offer already owns `destination_url`, and the redirect endpoint (outside KAN-14) will resolve it through `TrackingLink → Campaign → Offer → destination_url`. Storing it on the link would create a synchronization risk if the offer URL is updated after link generation. The canonical offer URL is the single source of truth.

### 2.4 Foreign key and indexes

- `campaign_id -> campaigns.id` uses `ON DELETE CASCADE`, matching the existing offer-to-campaign lifecycle and preventing orphan tracking links if campaign deletion is introduced.
- Laravel's `foreignId()->constrained()` creates the conventional index on `tracking_links.campaign_id` needed for joins, relationship loading, and cascade enforcement.
- `code` has a `UNIQUE` constraint at the database level, preventing duplicate codes regardless of application-level checks.
- `id` is the deterministic sort key and primary index.
- No other indexes are planned because KAN-14 has no listing, filtering, or sorting on tracking links.

## 3. Eloquent Design

### 3.1 `TrackingLink`

- `$fillable`: `campaign_id`, `code`.
- No enum or boolean casts beyond framework defaults.
- `campaign(): BelongsTo`.
- Ownership is never inferred from request input.

### 3.2 Relationship changes

- `Campaign::trackingLinks(): HasMany`.
- `TrackingLinkFactory` creates a Campaign by default and provides a state/helper for explicit campaign assignment.
- No `User::trackingLinks()` or `Offer::trackingLinks()` has-many-through relationship is implemented. KAN-14 has no listing endpoint that requires cross-aggregate queries.

## 4. Unique Code Strategy

### 4.1 Generation

`Str::random(32)` produces a 32-character alphanumeric string using `[A-Za-z0-9]`. The keyspace is `62^32 ≈ 7.4 × 10^57`, making random collisions astronomically unlikely under any realistic load.

### 4.2 Why not other strategies

| Strategy | Rejection reason |
|---|---|
| UUID v4 | 36 characters with hyphens; unnecessarily long and not optimally URL-safe |
| ULID | 26 characters; good but requires external package; `Str::random` is built-in and sufficient |
| Sequential/auto-increment | Predictable; exposes internal count; security concern |
| Database-generated identifiers | Laravel does not provide this natively; adds complexity |

### 4.3 Collision handling

A bounded retry strategy handles the extremely rare case of a collision. The Action retries **only** when the INSERT fails because of the UNIQUE constraint on `tracking_links.code`. Unrelated database exceptions are never swallowed or retried.

```php
private function generateUniqueCode(Campaign $campaign): TrackingLink
{
    $maxAttempts = 5;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $code = Str::random(32);

        try {
            return $campaign->trackingLinks()->create([
                'code' => $code,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                if ($attempt === $maxAttempts) {
                    throw new CannotGenerateTrackingLink(...);
                }
                continue;
            }

            throw $e;
        }
    }
}
```

The `isUniqueViolation` method inspects the SQLSTATE or driver error code to verify that the failure is specifically a unique-constraint violation on the `code` column. If the installed Laravel/Illuminate version provides a dedicated unique-constraint exception (e.g., `UniqueConstraintViolationException`), that is preferred. Any unrelated database exception is rethrown immediately.

The database UNIQUE constraint is the authoritative collision guard. The application-level retry exists only to convert a rare database error into a successful generation. No explicit transaction is needed because each iteration is a single atomic INSERT attempt.

### 4.4 Uniqueness guarantee

The UNIQUE index on `code` guarantees no two tracking links can share the same code, regardless of application behavior. Even if the retry strategy were removed, the database would reject duplicates.

## 5. Tracking URL Construction

### 5.1 Strategy

The full tracking URL is constructed using Laravel's URL generator:

```php
url('/t/' . $trackingLink->code)
```

This resolves `APP_URL` from the environment configuration automatically. Manual concatenation of `config('app.url')` is avoided.

### 5.2 No named route

A named route (`Route::name('tracking.redirect')`) would require defining the actual `GET /t/{code}` route in `routes/api.php` or `routes/web.php`. KAN-14 explicitly excludes the public redirect endpoint. Defining a route that does nothing would be misleading.

The URL points to the future KAN-15 public route. KAN-14 returns the URL even though redirect behavior is not yet implemented. `APP_URL` remains an environment/deployment configuration responsibility.

### 5.3 Redirect scope

The public redirect endpoint (`GET /t/{code}`) is explicitly out of scope for KAN-14. It will be implemented in a future story that also adds click recording and visit analytics. At that point, the route will be defined and a named route can be introduced.

## 6. One or Multiple Links per Campaign

### 6.1 Decision

A Campaign may generate **multiple tracking links**. There is no limit enforced at the database or application level in KAN-14.

### 6.2 Justification

The Jira requirement states: "a suspended Campaign cannot generate a new active tracking link." This implies a binary active/inactive distinction at the Campaign level, not a singleton constraint per Campaign.

Multiple links per Campaign allow affiliates to:
- Use different links for different traffic sources (though UTM parameters are not in KAN-14, the link code itself identifies the source).
- Replace a compromised link.
- A/B test different creatives with distinct tracking codes.

### 6.3 Repeated generation

A Campaign owner may call `POST /api/v1/campaigns/{campaign}/tracking-links` multiple times. Each call generates a new, independent tracking link. There is no idempotency constraint; the endpoint is intentionally non-idempotent.

## 7. Campaign-Status Rule

### 7.1 Status matrix

| Campaign status | Result |
|---|---|
| `active` | Allowed — generation proceeds |
| `draft` | Rejected — `422 Unprocessable Content` |
| `suspended` | Rejected — `422 Unprocessable Content` |

### 7.2 Error format

A rejected generation due to Campaign status returns:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": [
      "Only an active campaign can generate tracking links."
    ]
  }
}
```

The validation error is attached to the `status` key.

### 7.3 Why `422` instead of `409`

`409 Conflict` is reserved for lifecycle transition conflicts (activate/suspend with an invalid source status). Tracking-link generation is not a lifecycle transition; it is a creation operation with a business-validity precondition. The Campaign is structurally valid (exists, is owned) but its current status does not permit this specific operation.

`422 Unprocessable Content` is consistent with the existing pattern for archived Offer rejection (KAN-13 `StoreCampaignRequest`), which uses the same class of business-validity check: a structurally valid parent entity whose status disallows the requested operation.

### 7.4 Authorization-before-status ordering

The request processing order is:

1. `auth:sanctum` middleware → `401` for guests.
2. Route model binding resolves the Campaign → `404` if missing.
3. `GenerateTrackingLinkRequest::authorize()` calls `CampaignPolicy::generateTrackingLink` → `403` for foreign Campaigns.
4. `GenerateTrackingLinkRequest::after()` validates Campaign status → `422` for draft or suspended.
5. Controller receives the authorized and validated request.
6. Controller calls `GenerateTrackingLinkAction`.

This guarantees that a foreign inactive Campaign returns `403`, never `422`, regardless of its status.

## 8. Authorization

### 8.1 `CampaignPolicy::generateTrackingLink`

```php
public function generateTrackingLink(User $user, Campaign $campaign): bool
{
    return $this->ownsCampaign($user, $campaign);
}
```

Each policy method delegates to one private ownership check comparing `$campaign->offer->user_id` to `$user->id`. There is no `before()` method and no Admin bypass.

### 8.2 HTTP outcomes

| Actor/resource | Result |
|---|---|
| Guest | Sanctum returns `401` before Form Request or binding |
| Owner of active Campaign | Policy permits, status validation passes, generation proceeds |
| Owner of draft/suspended Campaign | Policy permits, status validation returns `422` |
| Authenticated non-owner, existing Campaign | Form Request `authorize()` returns `403` |
| Any authenticated user, missing Campaign | Route model binding returns `404` before Form Request |

### 8.3 Authorization mechanism

Authorization occurs in `GenerateTrackingLinkRequest::authorize()`, which retrieves the route-bound Campaign and checks the `generateTrackingLink` Policy ability. This is the primary authorization gate.

The controller may optionally repeat `Gate::authorize('generateTrackingLink', $campaign)` as defense-in-depth, matching the existing pattern in `CampaignController::activate` and `CampaignController::suspend`. However, authorization must already exist in the Form Request.

## 9. Form Request

### 9.1 `GenerateTrackingLinkRequest`

The Form Request performs authorization and campaign-status validation:

```php
final class GenerateTrackingLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        if (! $campaign instanceof Campaign) {
            return true;
        }

        return $this->user()?->can(
            'generateTrackingLink',
            $campaign,
        ) === true;
    }

    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $campaign = $this->route('campaign');

                if (! $campaign instanceof Campaign) {
                    return;
                }

                if ($campaign->status !== CampaignStatus::Active) {
                    $validator->errors()->add(
                        'status',
                        'Only an active campaign can generate tracking links.',
                    );
                }
            },
        ];
    }
}
```

Processing order within the Form Request:
1. `authorize()` resolves the route-bound Campaign and checks policy ownership.
2. Missing Campaign → `404` via route model binding (before `authorize()`).
3. Foreign Campaign → `403` from policy check in `authorize()`.
4. `after()` hook validates Campaign status → `422` with `status` error for draft or suspended.

Authorization-before-validation is enforced because `authorize()` runs before `rules()` and `after()`.

### 9.2 Missing Campaign

Missing Campaign returns `404` via Laravel's global route model binding, which occurs before the Form Request's `authorize()` method.

### 9.3 Foreign Campaign

Foreign Campaign returns `403` via `CampaignPolicy::generateTrackingLink` called in `GenerateTrackingLinkRequest::authorize()`.

## 10. Action

### 10.1 `GenerateTrackingLinkAction`

```php
public function execute(Campaign $campaign): TrackingLink
```

Responsibilities:
1. Receive an authorized, active Campaign.
2. Generate a unique code using `Str::random(32)`.
3. Create the `TrackingLink` record via `$campaign->trackingLinks()->create()`.
4. Handle collisions with a bounded retry (max 5 attempts) on verified unique violations only.
5. Return the persisted `TrackingLink`.

The Action:
- Avoids Request objects.
- Avoids authorization logic.
- Avoids direct HTTP responses.
- Does not check Campaign status (caller's responsibility).
- Does not check ownership (caller's responsibility).
- Does not swallow unrelated database exceptions.
- Does not perform application-level `exists()` checks.
- Does not use unbounded loops.

### 10.2 Transaction decision

No explicit database transaction is used. The Action performs a single INSERT per attempt, and the retry loop handles failures. A transaction would not provide meaningful benefit because:
- There are no multiple related writes to protect.
- The UNIQUE constraint is the collision guard, not transactional isolation.
- Each attempt is one atomic INSERT.

### 10.3 Collision retry

The retry catches `QueryException` and verifies it is a unique-constraint violation on `code` before retrying. The verification uses:
- Laravel's `UniqueConstraintViolationException` if available in the installed framework version; otherwise
- Inspection of the SQLSTATE or driver error code to confirm a verified unique violation.

Any unrelated database exception is rethrown immediately. After five verified unique-code collisions, a domain-level generation exception is thrown.

## 11. Controller and Routes

`CampaignController` has an additional `storeTrackingLink` method for tracking-link generation. It validates via `GenerateTrackingLinkRequest`, optionally re-authorizes with `Gate::authorize` as defense-in-depth, calls the Action, and serializes with `TrackingLinkResource`.

### 11.1 New route

| Method | URI | Controller | Route name |
|---|---|---|---|
| POST | `/api/v1/campaigns/{campaign}/tracking-links` | `CampaignController::storeTrackingLink` | `api.v1.campaigns.tracking-links.store` |

### 11.2 Route registration

Inside the existing `auth:sanctum` middleware group:

```php
Route::post('/campaigns/{campaign}/tracking-links', [CampaignController::class, 'storeTrackingLink'])
    ->name('api.v1.campaigns.tracking-links.store');
```

### 11.3 No other endpoints

No list, show, update, delete, deactivate, rotate, or redirect endpoints are added.

## 12. API Resource

`TrackingLinkResource` exposes exactly:

```json
{
  "id": 1,
  "campaign_id": 42,
  "code": "aB3dE7gH9jK1mN3pQ5rS7tU9vW1xY3z",
  "url": "http://localhost/t/aB3dE7gH9jK1mN3pQ5rS7tU9vW1xY3z",
  "created_at": "2026-07-29T12:00:00.000000Z",
  "updated_at": "2026-07-29T12:00:00.000000Z"
}
```

Fields:
- `id`: Tracking link primary key.
- `campaign_id`: Parent Campaign foreign key.
- `code`: The unique 32-character code.
- `url`: Full tracking URL generated with `url('/t/' . $code)`.
- `created_at`: ISO 8601 timestamp.
- `updated_at`: ISO 8601 timestamp.

Not exposed: `user_id` (does not exist), `offer_id`, `destination_url`, `is_active` (does not exist), Offer data, Campaign name, Campaign budget, Campaign traffic source, or any internal ownership chain.

## 13. Error and Response Contract

| Case | Status |
|---|---:|
| Create success | `201` |
| Guest | `401` |
| Existing foreign Campaign | `403` |
| Missing Campaign | `404` |
| Draft or suspended Campaign | `422` |
| Code collision after max retries | `500` (unrecoverable domain exception; should never happen with `Str::random(32)`) |

Authorization occurs before Campaign-status validation. Failed requests must not change database state.

## 14. Documentation Design

After implementation, update only the final KAN-14 state in `docs/conception-technique.md`:

- MCD relationships: Campaign generates TrackingLinks.
- MLD `tracking_links` table with exact schema, FK, and indexes.
- One authenticated route and response conventions.
- Indirect ownership, Action/Policy/Form Request boundaries, and scope exclusions.
- Mark KAN-14 implemented only after tests and verification pass.

Do not document speculative redirect, click, conversion, analytics, frontend, or AI designs.

## 15. Planned Production Files

Create after approval:

- `database/migrations/..._create_tracking_links_table.php`
- `app/Models/TrackingLink.php`
- `database/factories/TrackingLinkFactory.php`
- `app/Exceptions/CannotGenerateTrackingLink.php`
- `app/Actions/Campaign/GenerateTrackingLinkAction.php`
- `app/Http/Requests/Api/V1/Campaign/GenerateTrackingLinkRequest.php`
- `app/Http/Resources/Api/V1/TrackingLinkResource.php`
- `tests/Feature/Api/V1/TrackingLinkApiTest.php`

Modify after approval:

- `app/Models/Campaign.php`
- `app/Policies/CampaignPolicy.php`
- `app/Http/Controllers/Api/V1/CampaignController.php`
- `routes/api.php`
- `docs/conception-technique.md`
