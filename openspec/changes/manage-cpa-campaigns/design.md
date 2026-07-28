# Design - KAN-13: Creer et gerer les campagnes CPA

## 1. Existing Conventions Inspected

The design follows these implemented conventions:

- `routes/api.php`: `/api/v1` prefix, named routes, and `auth:sanctum` grouping.
- `Offer` and its migration: relationship-owned records, guarded ownership, enum and `decimal:2` casts.
- `OfferPolicy`: strict ownership with no Admin bypass and Laravel policy auto-discovery.
- `UpdateOfferRequest`: policy authorization before rule validation and true PATCH validation.
- Offer Actions: business mutations without authorization, request objects, or HTTP responses.
- `OfferResource`: money as an exact decimal string and dates using Laravel's current Carbon JSON serialization.
- `OfferController`: thin orchestration and 15-item pagination ordered by descending `id`.
- Offer Pest tests: Sanctum authentication, `RefreshDatabase`, persisted-state assertions, and `data`/`links`/`meta` pagination.
- `docs/conception-technique.md`: numbered French technical architecture covering MCD, MLD, enums, routes, conventions, implementation status, and open decisions.

## 2. Data Model

### 2.1 `campaigns` table

| Column | SQL/Laravel type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED`, `$table->id()` | No | auto increment | Primary key |
| `offer_id` | `BIGINT UNSIGNED`, `$table->foreignId('offer_id')` | No | none | FK to `offers.id` |
| `name` | `VARCHAR(255)`, `$table->string('name')` | No | none | Trimmed, non-empty |
| `traffic_source` | `VARCHAR(255)`, `$table->string('traffic_source')` | No | none | Trimmed free-form label |
| `budget` | `DECIMAL(12,2)`, `$table->decimal('budget', 12, 2)` | No | none | Range `0.00` to `9,999,999,999.99` |
| `status` | `VARCHAR(20)`, `$table->string('status', 20)` | No | `'draft'` | Cast to `CampaignStatus` |
| `created_at` | timestamp | Yes | framework-managed | `$table->timestamps()` convention |
| `updated_at` | timestamp | Yes | framework-managed | `$table->timestamps()` convention |

Migration definition:

```php
$table->id();
$table->foreignId('offer_id')
    ->constrained()
    ->cascadeOnDelete();
$table->string('name');
$table->string('traffic_source');
$table->decimal('budget', 12, 2);
$table->string('status', 20)->default(CampaignStatus::Draft->value);
$table->timestamps();
```

This is an additive `create_campaigns_table` migration. No destructive command or schema operation is permitted.

### 2.2 Direct `user_id` decision

`campaigns.user_id` will not exist. The offer is the campaign's aggregate parent and already stores the owner. Duplicating `user_id` would create two ownership values that could diverge, require synchronization, and allow inconsistent rows.

The expected campaign volume does not justify denormalization. User listing uses `Campaign::query()->whereHas('offer', fn ($query) => $query->where('user_id', $userId))`, which uses the existing `offers.user_id` index and campaign `offer_id` index. No `User::campaigns()` has-many-through relationship is required for KAN-13. A direct owner column should be reconsidered only with measured query evidence and a database-enforced consistency design.

### 2.3 Foreign key and indexes

- `offer_id -> offers.id` uses `ON DELETE CASCADE`, matching the existing user-to-offer lifecycle and preventing orphan campaigns if physical offer deletion is introduced or performed internally.
- Laravel's `foreignId()->constrained()` creates the conventional index on `campaigns.offer_id` needed for joins, relationship loading, and cascade enforcement.
- No separate `status` or `(offer_id, status)` index is planned because KAN-13 has no status filter and campaigns are listed across all of a user's offers.
- The existing `offers.user_id` leading column in `(user_id, status)` supports ownership qualification.
- `id` is the deterministic sort key and primary index.

## 3. Eloquent Design

### 3.1 `Campaign`

- `$fillable`: `name`, `traffic_source`, `budget`, `status`; `offer_id` is excluded.
- Casts: `budget => decimal:2`, `status => CampaignStatus::class`.
- `offer(): BelongsTo`.
- Ownership is never inferred from request input.

### 3.2 Relationship changes

- `Offer::campaigns(): HasMany`.
- `CampaignFactory` creates an Offer by default and provides states/helpers for explicit offer and status assignment.
- No `User::campaigns()` has-many-through relationship is implemented. Authenticated-user campaign listing is scoped through `Campaign::query()->whereHas('offer', user_id)`, which uses the existing indexed ownership path.

## 4. Campaign Status Lifecycle

### 4.1 Enum

`App\Enums\CampaignStatus` is a string-backed PHP enum with exactly:

```php
case Draft = 'draft';
case Active = 'active';
case Suspended = 'suspended';
```

No archived, completed, or deleted value is justified by KAN-13.

### 4.2 Initial status and PATCH behavior

- New campaigns always persist as `CampaignStatus::Draft`, set explicitly by `CreateCampaignAction` in addition to the database default.
- `status` is not a client-selectable Store input. `StoreCampaignRequest` marks it `prohibited`, so submitting any value, including `draft`, returns `422` rather than being ignored.
- General `PATCH /campaigns/{campaign}` accepts only `name`, `traffic_source`, and `budget`.
- Submitted `status` is explicitly prohibited and returns `422`, even when an editable field is also present.
- Only dedicated endpoints can activate or suspend.

### 4.3 Transitions

| Operation | From | To | Result |
|---|---|---|---|
| activate | draft | active | `200`, persisted |
| suspend | active | suspended | `200`, persisted |
| activate | suspended | active | `200`, persisted |
| activate | active | none | `409`, unchanged |
| suspend | draft | none | `409`, unchanged |
| suspend | suspended | none | `409`, unchanged |

Activation and suspension are intentionally non-idempotent because the route represents a lifecycle transition, not a desired-state setter. Repeated operations return `409 Conflict`, keep all data and `updated_at` unchanged, and use a stable JSON error such as:

```json
{
  "message": "The campaign cannot transition from active to active."
}
```

Actions throw a campaign-specific domain exception carrying source/target statuses. Laravel's exception configuration maps only that exception to HTTP `409`; Actions do not construct HTTP responses.

## 5. Traffic Source

`traffic_source` is a free-form validated string, not an enum. KAN-13 does not define an authoritative channel catalog, so an enum would invent unsupported values and require code deployment for every new source.

Rules are `required|string|max:255` on Store and `sometimes|required|string|max:255` on Update. String values are trimmed before validation; whitespace-only values become empty and fail `required`. The database stores the normalized display label as supplied. Search, taxonomy, and channel normalization are deferred.

## 6. Offer Resolution and Creation Authorization

`StoreCampaignRequest` and the controller enforce this exact creation order for a valid-shaped `offer_id`:

1. Resolve the Offer globally with an unscoped `Offer::findOrFail`, caching the model on the request; a missing Offer returns `404`.
2. Authorize `createCampaign` through `OfferPolicy`; an existing foreign Offer returns `403`.
3. Verify that the now-authorized owned Offer is not archived; an owned archived Offer returns `422` with an `offer_id` error.
4. Pass the authorized Offer and validated campaign inputs to `CreateCampaignAction`, which sets `CampaignStatus::Draft`.

An absent or malformed `offer_id` cannot identify a model and returns normal field validation `422`. Campaign field validation and archived-offer business validation never reveal details about an existing foreign Offer.

`OfferPolicy::createCampaign(User $user, Offer $offer)` performs only strict ownership. Archived status is a business-validity concern, not an authorization concern. This ordering ensures a foreign archived offer still returns `403`, not `422`.

`422 Unprocessable Content` is chosen for an owned archived offer because the request is structurally valid but `offer_id` identifies a parent that is invalid for campaign creation. This aligns with Laravel validation responses and gives clients a field-level error. `409` is reserved for campaign lifecycle conflicts.

## 7. Authorization

### 7.1 `CampaignPolicy`

The policy owns these methods:

```php
view(User $user, Campaign $campaign): bool
update(User $user, Campaign $campaign): bool
activate(User $user, Campaign $campaign): bool
suspend(User $user, Campaign $campaign): bool
```

Each delegates to one private ownership check comparing `$campaign->offer->user_id` to `$user->id`. There is no `before()` method and no Admin bypass.

### 7.2 HTTP outcomes

| Actor/resource | Result |
|---|---|
| Guest | Sanctum returns `401` before binding/controller behavior |
| Owner | Policy permits the requested operation |
| Authenticated non-owner, existing Campaign | Global route binding resolves it, policy returns `403` |
| Any authenticated user, missing Campaign | Global route binding returns `404` before policy invocation |

Index is secured structurally through `Campaign::query()->whereHas('offer', fn ($q) => $q->where('user_id', $request->user()->id))`. Store authorization belongs to the parent `OfferPolicy::createCampaign`. Update authorization occurs in `UpdateCampaignRequest::authorize()` before validation and is repeated with `Gate::authorize` in the controller as defense-in-depth, matching KAN-12. Show and lifecycle controllers call `Gate::authorize` before Action/resource work.

## 8. Form Requests

### 8.1 `StoreCampaignRequest`

| Field | Rules |
|---|---|
| `offer_id` | `required`, `integer`, `min:1` plus request-level resolution/authorization/business checks |
| `name` | `required`, `string`, `max:255` |
| `traffic_source` | `required`, `string`, `max:255` |
| `budget` | `required`, `numeric`, `min:0`, `max:9999999999.99`, `decimal:0,2` |
| `status` | `prohibited`; any submitted value returns `422` |

The request exposes the already resolved authorized `Offer` through a typed method for controller-to-Action handoff. No duplicate lookup is required.

### 8.2 `UpdateCampaignRequest`

- `authorize()` invokes `CampaignPolicy::update` on the route-bound campaign before validation.
- Editable fields use true PATCH rules: `sometimes|required` plus their Store constraints.
- `offer_id`, `user_id`, and `status` use `prohibited`, explicitly rejecting reassignment, fabricated direct ownership, and lifecycle bypass.
- An `after()` callback rejects a payload with none of `name`, `traffic_source`, or `budget`, including `{}`, with `422` under a `campaign` error key.
- Unknown non-protected fields do not mutate data; a payload containing only unknown fields still fails the editable-payload check.

### 8.3 Index request

No `IndexCampaignRequest` is planned. KAN-13 requires no search/filter query parameters, and Laravel already handles `page`. Status filtering offers limited value for the stated criteria and would add validation, scope, index, and pagination-link contracts outside the minimum story.

## 9. Actions

### 9.1 `CreateCampaignAction`

`execute(Offer $offer, string $name, string $trafficSource, string|int|float $budget): Campaign`

- Creates via `$offer->campaigns()->create()` so `offer_id` cannot come from public input.
- Sets status explicitly to `CampaignStatus::Draft`; the Action accepts no status argument.
- Returns the refreshed campaign with `offer` loaded.

### 9.2 `UpdateCampaignAction`

`execute(Campaign $campaign, array $fields): Campaign`

- Applies an internal whitelist of `name`, `traffic_source`, and `budget`.
- Cannot change `offer_id`, `user_id`, or `status`.
- Saves and returns the refreshed campaign with `offer` loaded.

### 9.3 `ActivateCampaignAction`

`execute(Campaign $campaign): Campaign`

- Allows `draft -> active` and `suspended -> active`.
- Throws the domain transition exception for `active -> active`.
- Saves only a valid transition and returns the refreshed campaign with `offer` loaded.

### 9.4 `SuspendCampaignAction`

`execute(Campaign $campaign): Campaign`

- Allows only `active -> suspended`.
- Throws the domain transition exception from `draft` or `suspended`.
- Saves only a valid transition and returns the refreshed campaign with `offer` loaded.

All Actions receive validated domain inputs, contain mutations, and contain no authorization, request-object, or response logic.

## 10. Controller and Routes

`CampaignController` has `index`, `store`, `show`, `update`, `activate`, and `suspend`. It authorizes where needed, calls Actions for mutations, eager-loads Offer context, and serializes with `CampaignResource`.

All routes are inside the existing `Route::prefix('v1')` and `auth:sanctum` groups:

| Method | URI | Controller | Route name |
|---|---|---|---|
| GET | `/api/v1/campaigns` | `index` | `api.v1.campaigns.index` |
| POST | `/api/v1/campaigns` | `store` | `api.v1.campaigns.store` |
| GET | `/api/v1/campaigns/{campaign}` | `show` | `api.v1.campaigns.show` |
| PATCH | `/api/v1/campaigns/{campaign}` | `update` | `api.v1.campaigns.update` |
| POST | `/api/v1/campaigns/{campaign}/activate` | `activate` | `api.v1.campaigns.activate` |
| POST | `/api/v1/campaigns/{campaign}/suspend` | `suspend` | `api.v1.campaigns.suspend` |

No delete, archive, restore, tracking-link, or conversion route is added.

## 11. Listing and Pagination

The index query starts from `Campaign::query()->whereHas('offer', fn ($q) => $q->where('user_id', $userId))`, explicitly eager-loads only `offer:id,name`, orders by `campaigns.id DESC`, and calls `paginate(15)`. `CampaignResource` must consume that loaded relation and listing tests must prevent lazy loading (or otherwise assert the relation is loaded) so the collection cannot regress to N+1 queries.

The response preserves the existing Laravel Resource collection structure:

- `data`: flat CampaignResource array.
- `links`: `first`, `last`, `prev`, `next`.
- `meta`: `current_page`, `last_page`, `per_page`, `total`.

No filter query strings need preservation because no filters are introduced. Campaign IDs provide deterministic newest-first ordering.

## 12. API Resource

`CampaignResource` exposes:

```json
{
  "id": 42,
  "offer": { "id": 7, "name": "Fitness Offer" },
  "name": "TikTok July",
  "traffic_source": "TikTok Ads",
  "budget": "1500.00",
  "status": "draft",
  "created_at": "2026-07-23T12:00:00.000000Z",
  "updated_at": "2026-07-23T12:00:00.000000Z"
}
```

The nested Offer summary identifies the parent without exposing owner, destination URL, payout, or description. Budget uses the model's `decimal:2` string cast. Dates are passed through as Carbon values exactly as `OfferResource` does, preserving framework serialization. Single-resource responses use `{"data":{"campaign": ...}}`; collection responses use the pagination structure above.

## 13. Error and Response Contract

| Case | Status |
|---|---:|
| Create success | `201` |
| List/show/update/activate/suspend success | `200` |
| Guest | `401` |
| Existing foreign Offer/Campaign | `403` |
| Missing Offer/Campaign | `404` |
| Field, empty PATCH, protected field, or archived owned Offer error | `422` |
| Invalid or repeated campaign lifecycle transition | `409` |

Authorization occurs before field/business validation whenever an existing parent or campaign can belong to another user. Failed requests must not change database state.

## 14. Documentation Design

After implementation, update only the final KAN-13 state in `docs/conception-technique.md`:

- MCD relationships: User owns Offers; Offer has Campaigns.
- MLD `campaigns` table with exact schema, FK, and indexes.
- `CampaignStatus` values and transition matrix.
- Six authenticated routes and response conventions.
- Indirect ownership, immutable `offer_id`, Action/Policy/Form Request boundaries, pagination, and scope exclusions.
- Mark KAN-13 implemented only after tests and verification pass.

Do not document speculative tracking, conversion, analytics, frontend, or Admin designs.

## 15. Planned Production Files

Create after approval:

- `database/migrations/..._create_campaigns_table.php`
- `app/Enums/CampaignStatus.php` (reused from KAN-8, unchanged during KAN-13)
- `app/Models/Campaign.php`
- `database/factories/CampaignFactory.php`
- `app/Policies/CampaignPolicy.php`
- `app/Actions/Campaign/CreateCampaignAction.php`
- `app/Actions/Campaign/UpdateCampaignAction.php`
- `app/Actions/Campaign/ActivateCampaignAction.php`
- `app/Actions/Campaign/SuspendCampaignAction.php`
- `app/Exceptions/InvalidCampaignTransition.php`
- `app/Http/Requests/Api/V1/Campaign/StoreCampaignRequest.php`
- `app/Http/Requests/Api/V1/Campaign/UpdateCampaignRequest.php`
- `app/Http/Resources/Api/V1/CampaignResource.php`
- `app/Http/Controllers/Api/V1/CampaignController.php`
- `tests/Feature/Api/V1/CampaignManagementApiTest.php`

Modify after approval:

- `app/Models/Offer.php`
- `app/Policies/OfferPolicy.php`
- `bootstrap/app.php`
- `routes/api.php`
- `docs/conception-technique.md`
