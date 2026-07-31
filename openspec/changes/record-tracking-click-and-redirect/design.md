# Design - KAN-15: Enregistrer un clic et rediriger vers l'offre

## 1. Existing Conventions Inspected

The design follows these implemented conventions:

- `routes/web.php`: Breeze web routes, `auth` middleware group, named routes.
- `routes/api.php`: `/api/v1` prefix, `auth:sanctum` grouping, named routes.
- `TrackingLink` model: `campaign()` BelongsTo, `code` fillable, factory with `Str::random(32)`.
- `Campaign` model: `offer()` BelongsTo, `trackingLinks()` HasMany, `CampaignStatus` enum cast.
- `Offer` model: `destination_url` VARCHAR 2048, `user()` BelongsTo.
- `CampaignStatus` enum: `Draft`, `Active`, `Suspended` cases.
- `OfferStatus` enum: `Draft`, `Active`, `Suspended`, `Archived` cases.
- `bootstrap/app.php`: exception rendering for domain exceptions, middleware alias registration.
- `GenerateTrackingLinkAction`: Action pattern with `execute()` method, no HTTP awareness.
- `TrackingCodeGenerator`: Service class for code generation.
- Pest feature tests: `RefreshDatabase`, `Sanctum::actingAs()`, `assertDatabaseHas/Count/Missing`.
- `docs/conception-technique.md`: MCD shows `LIEN_TRACKING ||--o{ CLIC` relationship.
- `composer.json`: Laravel 13.8, PHP ^8.3, Pest ^4.7.

## 2. Data Model

### 2.1 `tracking_clicks` table

| Column | SQL/Laravel type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED`, `$table->id()` | No | auto increment | Primary key |
| `tracking_link_id` | `BIGINT UNSIGNED`, `$table->foreignId('tracking_link_id')` | No | none | FK to `tracking_links.id` |
| `ip_hash` | `VARCHAR(64)`, `$table->string('ip_hash', 64)->nullable()` | Yes | null | HMAC-SHA256 of normalized IP |
| `user_agent` | `VARCHAR(512)`, `$table->string('user_agent', 512)->nullable()` | Yes | null | Truncated Visitor User-Agent |
| `referer` | `VARCHAR(2048)`, `$table->string('referer', 2048)->nullable()` | Yes | null | Truncated HTTP Referer |
| `utm_source` | `VARCHAR(255)`, `$table->string('utm_source', 255)->nullable()` | Yes | null | utm_source query parameter |
| `utm_medium` | `VARCHAR(255)`, `$table->string('utm_medium', 255)->nullable()` | Yes | null | utm_medium query parameter |
| `utm_campaign` | `VARCHAR(255)`, `$table->string('utm_campaign', 255)->nullable()` | Yes | null | utm_campaign query parameter |
| `utm_term` | `VARCHAR(255)`, `$table->string('utm_term', 255)->nullable()` | Yes | null | utm_term query parameter |
| `utm_content` | `VARCHAR(255)`, `$table->string('utm_content', 255)->nullable()` | Yes | null | utm_content query parameter |
| `created_at` | timestamp | Yes | framework-managed | `$table->timestamps()` convention — authoritative click time |
| `updated_at` | timestamp | Yes | framework-managed | `$table->timestamps()` convention |

Migration definition:

```php
$table->id();
$table->foreignId('tracking_link_id')
    ->constrained()
    ->cascadeOnDelete();
$table->string('ip_hash', 64)->nullable();
$table->string('user_agent', 512)->nullable();
$table->string('referer', 2048)->nullable();
$table->string('utm_source', 255)->nullable();
$table->string('utm_medium', 255)->nullable();
$table->string('utm_campaign', 255)->nullable();
$table->string('utm_term', 255)->nullable();
$table->string('utm_content', 255)->nullable();
$table->timestamps();
```

This is an additive `create_tracking_clicks_table` migration. No destructive command or schema operation is permitted.

Schema confirmation:
- No `clicked_at` — synchronous recording means `created_at` is the authoritative click timestamp.
- No `campaign_id` — derivable through `TrackingLink → campaign_id`.
- No `offer_id` — derivable through `TrackingLink → Campaign → Offer`.
- No `user_id` — anonymous visitors have no authenticated identity.
- No `ip_address` (raw) — privacy; raw IP must never be stored.
- No `destination_url` — derivable through `TrackingLink → Campaign → Offer → destination_url`.
- No `click_count` — denormalized counter; unnecessary with a clicks table.

### 2.2 Schema Justification

**`tracking_link_id`** — FK to `tracking_links.id` with `ON DELETE CASCADE`. Deleting a tracking link removes its click history. This matches the existing cascade pattern: `offers → campaigns → tracking_links → tracking_clicks`.

**`ip_hash`** — VARCHAR(64), nullable. Stores HMAC-SHA256 of the normalized visitor IP using a purpose-separated derived key. Nullable because the IP may not be available (e.g., behind certain proxies or in test environments). Length 64 accommodates hex-encoded SHA-256 output (64 characters).

**`user_agent`** — VARCHAR(512), nullable. Stores the HTTP User-Agent header, truncated to 512 characters. Nullable because the header may be absent. 512 characters captures the meaningful portion of most User-Agent strings while preventing oversized storage.

**`referer`** — VARCHAR(2048), nullable. Stores the HTTP Referer header, truncated to 2048 characters. Nullable because the header is often absent. 2048 matches the `destination_url` column length convention.

**`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`** — VARCHAR(255), nullable. Standard UTM query parameters. Nullable because they are optional. 255 characters matches the existing string column convention.

**Fields deliberately excluded:**

| Excluded field | Reason |
|---|---|
| `clicked_at` | Synchronous recording — `created_at` is the authoritative click time |
| `destination_url` | Redundant — derivable through `TrackingLink → Campaign → Offer → destination_url` |
| `campaign_id` | Redundant — derivable through `TrackingLink → campaign_id` |
| `user_id` | Anonymous visitors have no authenticated identity |
| `ip_address` (raw) | Privacy — raw IP must never be stored |
| `country`, `city`, `region` | Speculative geolocation — out of scope |
| `device_type`, `browser`, `os` | Speculative device parsing — out of scope |
| `is_unique` | Unique visitor counting is out of scope |
| `converted_at` | Conversion tracking is a separate story |
| `session_id` | Cookie/session tracking is out of scope |
| `click_count` on tracking_links | Denormalized counter — unnecessary with a clicks table |

### 2.3 Foreign key and indexes

- `tracking_link_id → tracking_links.id` uses `ON DELETE CASCADE`, preventing orphan clicks if a tracking link is deleted.
- Laravel's `foreignId()->constrained()` creates the conventional index on `tracking_clicks.tracking_link_id` needed for joins, relationship loading, and cascade enforcement.
- `id` is the deterministic sort key and primary index.
- No other indexes are planned in KAN-15. Future analytics queries may benefit from indexes on `created_at` or composite indexes, but these should be added when query patterns are measured.

## 3. Eloquent Design

### 3.1 `TrackingClick`

- `$fillable`: `tracking_link_id`, `ip_hash`, `user_agent`, `referer`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`.
- `trackingLink(): BelongsTo`.
- No ownership inference from request input.
- No `clicked_at` field — `created_at` is the click timestamp.

### 3.2 Relationship additions

- `TrackingLink::clicks(): HasMany`.
- `TrackingClick::trackingLink(): BelongsTo`.
- `TrackingClickFactory` creates a TrackingLink by default and provides a state/helper for explicit TrackingLink assignment.
- No `Campaign::clicks()` or `Offer::clicks()` has-many-through relationship is implemented. KAN-15 has no listing endpoint that requires cross-aggregate queries.

## 4. IP Privacy

### 4.1 Purpose-separated HMAC key

Raw IP addresses are never stored. The visitor's IP is hashed using HMAC-SHA256 with a purpose-separated key derived from `APP_KEY`:

```php
$hashingKey = hash_hmac(
    'sha256',
    'tracking-ip-hash:v1',
    (string) config('app.key'),
    true,
);

$hash = hash_hmac('sha256', $normalizedIp, $hashingKey);
```

The context string `tracking-ip-hash:v1` ensures that even if `APP_KEY` is used for other HMAC operations, the derived keys are domain-separated. The `true` parameter returns raw binary output for use as the HMAC key.

### 4.2 Why not plain SHA-256

Plain SHA-256 without a secret is a publicly known algorithm. An attacker with access to the database could use rainbow tables or brute-force common IP addresses (especially IPv4, which has only ~4 billion values). HMAC with a derived key makes precomputation infeasible without the key.

### 4.3 Key source and rotation

`APP_KEY` is a base64-encoded 32-byte key that Laravel requires for encryption, session signing, and CSRF protection. It is already a deployed secret. The purpose-separated derived key avoids introducing a new configuration variable.

Rotating `APP_KEY` changes all future IP hashes. This is acceptable because KAN-15 does not implement unique-visitor attribution — each click is an independent record. Historical hashes remain valid for their row but will not match new hashes for the same IP after rotation.

### 4.4 IP normalization (both IPv4 and IPv6)

Both IPv4 and IPv6 are normalized using `inet_pton()` followed by `inet_ntop()`:

```php
private function normalizeIp(string $ip): ?string
{
    $ip = trim($ip);

    if ($ip === '') {
        return null;
    }

    // Strip zone identifier for IPv6 (e.g., %eth0)
    $ip = preg_replace('/%[a-zA-Z0-9]+$/', '', $ip);

    $packed = @inet_pton($ip);

    if ($packed === false) {
        return null;
    }

    $normalized = inet_ntop($packed);

    if ($normalized === false) {
        return null;
    }

    return $normalized;
}
```

- `inet_pton()` parses the IP into a canonical packed binary form.
- `inet_ntop()` produces the canonical text representation.
- For IPv6, this expands compressed forms (e.g., `2001:db8::1` → `2001:db8:0:0:0:0:0:1`), lowercases hex, and strips leading zeros.
- No manual hex segment normalization is needed — `inet_ntop()` handles it.
- Zone identifiers (e.g., `%eth0`) are stripped before validation because `inet_pton()` does not accept them.

### 4.5 When IP is unavailable or invalid

If `$request->ip()` returns `null`, an empty string, or `inet_pton()` fails (invalid IP), `ip_hash` is set to `null`. The click is still recorded with available metadata. This is a valid state — the nullable column supports it.

### 4.6 IP hash in responses

The `ip_hash` value is never exposed in any HTTP response. The redirect endpoint returns only a `302` redirect response, not JSON.

## 5. Missing Relationship Handling

### 5.1 TrackingLink with no Campaign

If the `campaign_id` foreign key references a deleted Campaign, the FK cascade (`tracking_links.campaign_id → campaigns.id ON DELETE CASCADE`) would have already removed the TrackingLink. However, as defense-in-depth, the controller checks for null after eager-loading:

```php
if (
    $trackingLink === null ||
    $trackingLink->campaign === null ||
    $trackingLink->campaign->offer === null ||
    $trackingLink->campaign->status !== CampaignStatus::Active
) {
    abort(404);
}
```

This prevents a property-access exception if the relationship chain is unexpectedly broken.

### 5.2 Campaign with no Offer

Same defense-in-depth pattern. If `offer_id` references a deleted Offer, the FK cascade would have already removed the Campaign and its TrackingLinks. The null check in the controller handles this case without throwing.

### 5.3 No click recorded for missing relations

When Campaign or Offer is null, the controller returns `404` before calling the click recording Action. No TrackingClick is created for broken relationship chains.

## 6. Campaign Status Behavior

### 6.1 Status matrix for public redirect

| TrackingLink state | Campaign status | Result |
|---|---|---|
| Exists, active Campaign | `active` | Record click, redirect `302` to Offer.destination_url |
| Exists, draft Campaign | `draft` | `404 Not Found` |
| Exists, suspended Campaign | `suspended` | `404 Not Found` |
| Missing code | — | `404 Not Found` |
| Campaign or Offer deleted | — | `404 Not Found` (FK cascade removes tracking link) |
| Campaign or Offer null after load | — | `404 Not Found` (defense-in-depth) |

### 6.2 Why 404 for inactive campaigns

Returning `404` for draft and suspended Campaigns prevents leaking private Campaign state. An attacker probing tracking codes learns nothing about whether the code is valid, whether a Campaign exists, or what its current status is. The response is identical to an unknown code.

Alternative considered:
- `410 Gone` — implies the resource permanently existed and was removed, which is misleading for a draft Campaign that was never public.
- Custom response with status information — leaks Campaign state.

## 7. Destination URL Safety

### 7.1 Current Offer validation

The existing `StoreOfferRequest` validates `destination_url` with `url:http,https`, which ensures only `http://` and `https://` URLs are accepted at creation time. This is validated by Laravel's `Url` rule with the `http` and `https` schemes.

### 7.2 Defense-in-depth check

Despite the creation-time validation, KAN-15 performs a defense-in-depth check before every redirect:

```php
private function isSafeDestination(string $url): bool
{
    if (! filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    $host = parse_url($url, PHP_URL_HOST);

    return in_array(strtolower((string) $scheme), ['http', 'https'], true)
        && $host !== null
        && $host !== '';
}
```

The check requires all of:
1. `filter_var(FILTER_VALIDATE_URL)` succeeds — the URL is well-formed.
2. Scheme is exactly `http` or `https` (case-insensitive via `strtolower`).
3. Host exists and is not empty.

If any condition fails, the controller returns `404` instead of redirecting. This protects against:
- `javascript:alert(1)` — fails FILTER_VALIDATE_URL and scheme check.
- `data:text/html,...` — fails scheme check.
- `file:///etc/passwd` — fails scheme check.
- Relative URLs (e.g., `/admin`) — fails FILTER_VALIDATE_URL.
- Malformed http/https values — fails FILTER_VALIDATE_URL.
- URLs without a valid host — fails host check.

### 7.3 No host allowlist

A host allowlist is not introduced because:
- The existing system has no domain restriction requirements.
- Offer owners control their own destination URLs.
- An allowlist would require ongoing maintenance and create friction for legitimate use.

### 7.4 Redirect response

The redirect uses `302 Found` because:
- It is the standard HTTP status for temporary redirects.
- Browsers follow `302` automatically.
- The redirect is not cacheable by default (unlike `301`).
- The destination URL may change if the Offer is updated.

## 8. Click Recording Must Not Block Redirect

### 8.1 Strategy: synchronous best-effort

The `RecordTrackingClickAction` is called synchronously within the controller. Only the click recording is wrapped in a try-catch. TrackingLink resolution, Campaign validation, and destination URL validation are NOT inside the catch block.

```php
$trackingLink = TrackingLink::with('campaign.offer')
    ->where('code', $code)
    ->first();

if (
    $trackingLink === null ||
    $trackingLink->campaign === null ||
    $trackingLink->campaign->offer === null ||
    $trackingLink->campaign->status !== CampaignStatus::Active
) {
    abort(404);
}

$destinationUrl = $trackingLink->campaign->offer->destination_url;

if (! $this->isSafeDestination($destinationUrl)) {
    abort(404);
}

try {
    $action->execute($trackingLink, request());
} catch (\Throwable $e) {
    report($e);
}

return redirect($destinationUrl, 302);
```

This ensures:
- Routing and resolution defects are NOT swallowed.
- Campaign/Offer validation defects are NOT swallowed.
- Only click persistence failures are caught and logged.
- The redirect always proceeds for valid, safe links.

### 8.2 Why not a queued job

- No queue jobs exist in the codebase yet. Introducing queue infrastructure for a single click recording adds unnecessary complexity.
- The queue worker is configured but has no actual jobs. Adding the first job for click recording creates an implicit dependency on queue health for basic tracking functionality.
- Synchronous recording provides immediate consistency — the click exists in the database as soon as the redirect response is sent.
- Click volume during initial deployment is expected to be moderate. Queue-based recording is a future optimization, not a KAN-15 requirement.

### 8.3 Why not an event/listener

- Events and listeners in Laravel run synchronously within the same request unless a queue driver is configured. This provides no failure isolation benefit over a direct Action call.
- Adding events introduces an unnecessary abstraction layer for a single-use case.

### 8.4 Failure behavior

- The `report()` function sends the exception to Laravel's exception handler, which logs it to the configured log channel.
- No database error details are exposed to the visitor.
- The visitor receives the same `302` redirect regardless of click recording success.

### 8.5 Action responsibilities

`RecordTrackingClickAction::execute(TrackingLink $trackingLink, Request $request)`:
1. Extract visitor metadata from the Request.
2. Hash the IP using the `IpHasher` service.
3. Truncate oversized values with multibyte-safe `mb_substr`.
4. Create the `TrackingClick` record through the TrackingLink relationship.
5. Return the persisted `TrackingClick`.
6. Throw on failure (caller catches).

The Action does not:
- Handle HTTP responses.
- Catch its own exceptions.
- Validate Campaign status (caller's responsibility).
- Access the Offer or destination URL.
- Resolve missing Campaign or Offer relationships.

## 9. Request Metadata and UTM Validation

### 9.1 Reading metadata

| Source | Request accessor | Max length | Nullable |
|---|---|---|---|
| Visitor IP | `$request->ip()` | N/A (hashed) | Yes |
| User-Agent | `$request->header('User-Agent')` | 512 | Yes |
| Referer | `$request->header('Referer')` | 2048 | Yes |
| utm_source | `$request->query('utm_source')` | 255 | Yes |
| utm_medium | `$request->query('utm_medium')` | 255 | Yes |
| utm_campaign | `$request->query('utm_campaign')` | 255 | Yes |
| utm_term | `$request->query('utm_term')` | 255 | Yes |
| utm_content | `$request->query('utm_content')` | 255 | Yes |

### 9.2 Normalization rules

- All string values are trimmed of leading/trailing whitespace.
- Values exceeding the column maximum length are truncated using multibyte-safe `mb_substr`.
- Empty strings after trimming are stored as `null`.
- No URL decoding is applied to UTM values — they are read as-is from the query string.
- No encoding or escaping is applied at storage time — Laravel's Eloquent handles parameter binding.

### 9.3 Invalid or oversized values

Because this is a public tracking redirect:
- Invalid UTM values are not rejected — they are truncated and stored.
- Rejection would require a Form Request, adding complexity and potential redirect failures.
- Truncation preserves redirect reliability while preventing database failures.

### 9.4 Truncation implementation

```php
private function truncate(?string $value, int $max): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    return mb_substr($trimmed, 0, $max);
}
```

## 10. Redirect Response

### 10.1 HTTP status: 302 Found

```php
return redirect($destinationUrl, 302);
```

`302 Found` is the standard status for a temporary redirect. It:
- Is followed automatically by browsers.
- Is not cached by default.
- Appropriately represents a tracking redirect that may change.

`307 Temporary Redirect` was considered but rejected because it preserves the request method, which is irrelevant for a `GET` endpoint and adds no benefit.

`301 Moved Permanently` was rejected because tracking redirects are not permanent — the destination URL may change.

### 10.2 Destination URL resolution

```
TrackingLink → Campaign → Offer → destination_url
```

Bounded eager-loading query set:

```php
$trackingLink = TrackingLink::with('campaign.offer')
    ->where('code', $code)
    ->first();
```

This loads the required TrackingLink, Campaign, and Offer relations efficiently. Laravel's eager loading prevents N+1 behavior by loading related models in bounded queries (one query per relation level) rather than per-record lazy loading. If any link in the chain is missing (Campaign deleted, Offer deleted, or null after load), the controller returns `404`.

## 11. Architecture

### 11.1 Component responsibilities

| Component | Responsibility |
|---|---|
| `RedirectTrackingLinkController` | Resolve TrackingLink, verify Campaign active, verify Offer exists, check destination URL safety, call Action in try-catch, redirect |
| `RecordTrackingClickAction` | Extract metadata, hash IP via IpHasher, truncate, persist TrackingClick |
| `TrackingClick` model | Eloquent representation of a click record |
| `TrackingLink::clicks()` | HasMany relationship to TrackingClick |
| `TrackingClick::trackingLink()` | BelongsTo relationship to TrackingLink |
| `IpHasher` service | Purpose-separated HMAC-SHA256 IP hashing with inet_pton/inet_ntop normalization |
| `TrackingClickFactory` | Test data generation |

### 11.2 Controller design

```php
class RedirectTrackingLinkController extends Controller
{
    public function __invoke(
        string $code,
        RecordTrackingClickAction $action,
    ): RedirectResponse {
        $trackingLink = TrackingLink::with('campaign.offer')
            ->where('code', $code)
            ->first();

        if (
            $trackingLink === null ||
            $trackingLink->campaign === null ||
            $trackingLink->campaign->offer === null ||
            $trackingLink->campaign->status !== CampaignStatus::Active
        ) {
            abort(404);
        }

        $destinationUrl = $trackingLink->campaign->offer->destination_url;

        if (! $this->isSafeDestination($destinationUrl)) {
            abort(404);
        }

        try {
            $action->execute($trackingLink, request());
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect($destinationUrl, 302);
    }

    private function isSafeDestination(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return in_array(strtolower((string) $scheme), ['http', 'https'], true)
            && $host !== null
            && $host !== '';
    }
}
```

### 11.3 No Form Request

The public redirect endpoint does not use a Form Request because:
- There is no authenticated user to authorize.
- There is no request body to validate.
- UTM query parameters are read directly without validation.
- The route parameter `{code}` is resolved manually (not via route model binding) to control the 404 behavior and null-relation handling.

### 11.4 No Policy

The public redirect endpoint does not use a Policy because:
- There is no authenticated user.
- Access control is based on Campaign status, not ownership.

## 12. Eloquent Relationships

### 12.1 `TrackingLink::clicks()`

```php
public function clicks(): HasMany
{
    return $this->hasMany(TrackingClick::class);
}
```

### 12.2 `TrackingClick::trackingLink()`

```php
public function trackingLink(): BelongsTo
{
    return $this->belongsTo(TrackingLink::class);
}
```

Both relationships are required for a complete domain model and deterministic relationship tests.

### 12.3 Cascade behavior

- Deleting a `TrackingLink` cascades to its `TrackingClick` records via `ON DELETE CASCADE` on `tracking_clicks.tracking_link_id`.
- Deleting a `Campaign` cascades to its `TrackingLink` records, which cascades to their `TrackingClick` records.

## 13. Postman/Newman Collection

An import-ready Collection v2.1 file will be created covering:

| Test | Method | Expected |
|---|---|---|
| Active link redirect | GET `/t/{code}` | `302` with correct Location |
| Click persisted | GET `/t/{code}` | `tracking_clicks` row exists |
| Unknown code | GET `/t/nonexistent` | `404` |
| Draft Campaign | GET `/t/{draft_code}` | `404` |
| Suspended Campaign | GET `/t/{suspended_code}` | `404` |
| Unsafe destination | GET `/t/{unsafe_code}` | `404` |
| No authentication required | GET `/t/{code}` | `302` (no auth header) |
| UTM flow | GET `/t/{code}?utm_source=...` | `302`, UTM fields stored |
| KAN-14 link works | GET `/t/{kan14_generated_code}` | `302` |

Persistence-failure behavior is NOT tested through Postman/Newman because it requires deterministic database error injection. This behavior remains covered by Pest tests.

## 14. Documentation Design

After implementation, update only the final KAN-15 state in `docs/conception-technique.md`:

- MCD: `LIEN_TRACKING ||--o{ CLIC` relationship.
- MLD: `tracking_clicks` table with exact schema, FK, and indexes.
- Public route: `GET /t/{code}`.
- Architecture: public redirect flow, IP hashing, click recording, failure handling.
- Implemented status for KAN-15.

Do not document conversion, analytics, dashboard, AI, or frontend designs.

## 15. Planned Production Files

Create after approval:

- `database/migrations/..._create_tracking_clicks_table.php`
- `app/Models/TrackingClick.php`
- `database/factories/TrackingClickFactory.php`
- `app/Actions/TrackingLink/RecordTrackingClickAction.php`
- `app/Services/TrackingLink/IpHasher.php`
- `app/Http/Controllers/RedirectTrackingLinkController.php`
- `tests/Feature/TrackingRedirectTest.php`
- `postman/CPAFlow-AI-KAN-15.postman_collection.json`

Modify after approval:

- `app/Models/TrackingLink.php` — add `clicks()` relationship
- `routes/web.php` — add public `GET /t/{code}` route
- `docs/conception-technique.md` — update KAN-15 implementation status
