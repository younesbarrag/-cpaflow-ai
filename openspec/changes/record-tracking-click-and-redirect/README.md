# KAN-15 - Record Tracking Click and Redirect

## Status

Implemented and verified. KAN-15 is complete.

## Story

As a visitor, I want to click a tracking link and be redirected to the offer so the affiliate can measure visits.

Branch: `feature/KAN-15-record-click-and-redirect`

## Package

- `proposal.md`: problem, objectives, scope, risks, and approval gate.
- `design.md`: exact schema, purpose-separated IP hashing, canonical IP normalization, campaign status behavior, destination safety, failure handling, controller, Action, relationships, Postman/Newman scope, and documentation design.
- `spec.md`: normative requirements, scenarios, HTTP behavior, and acceptance mapping.
- `tasks.md`: 83 independently verifiable, unchecked implementation tasks.
- `README.md`: planning summary and key decisions.

## Key Decisions

| Topic | Decision |
|---|---|
| Public route | `GET /t/{code}` in `routes/web.php` — no auth, no `/api/v1` |
| Route name | `tracking.redirect` |
| Campaign status check | Active proceeds; draft/suspended returns `404` |
| Unknown code | `404` — identical to inactive campaign, no state leak |
| IP privacy | HMAC-SHA256 with purpose-separated derived key from `APP_KEY`; raw IP never stored |
| IP normalization | `inet_pton()` + `inet_ntop()` for both IPv4 and IPv6; zone ID stripped |
| Click failure | Best-effort synchronous; exception logged via `report()`; redirect always proceeds |
| Redirect status | `302 Found` |
| Destination safety | `filter_var(FILTER_VALIDATE_URL)` + scheme http/https (case-insensitive) + non-empty host |
| Click timestamp | `created_at` is the authoritative click time (synchronous recording) |
| Missing relations | Null-safe checks for Campaign and Offer; returns `404` |
| UTM parameters | 5 standard fields, read from query string, truncated to 255 chars |
| User-Agent | Read from header, truncated to 512 chars |
| Referer | Read from header, truncated to 2048 chars |
| Truncation | Multibyte-safe `mb_substr`, not rejected |
| Queue infrastructure | Not used — synchronous best-effort is sufficient |
| Schema | `id`, `tracking_link_id` FK CASCADE, `ip_hash` nullable, `user_agent`, `referer`, 5 UTM fields, `created_at`, `updated_at` — no `clicked_at` |

## Planned Endpoint

| Method | URI | Route name |
|---|---|---|
| GET | `/t/{code}` | `tracking.redirect` |

Route is in `routes/web.php`, public, no authentication middleware.

## Planned Schema

`tracking_clicks` table:

| Column | Type | Nullable | Notes |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | auto increment |
| tracking_link_id | BIGINT UNSIGNED FK | No | CASCADE on delete |
| ip_hash | VARCHAR(64) | Yes | Purpose-separated HMAC-SHA256 |
| user_agent | VARCHAR(512) | Yes | Truncated (mb_substr) |
| referer | VARCHAR(2048) | Yes | Truncated (mb_substr) |
| utm_source | VARCHAR(255) | Yes | Query param |
| utm_medium | VARCHAR(255) | Yes | Query param |
| utm_campaign | VARCHAR(255) | Yes | Query param |
| utm_term | VARCHAR(255) | Yes | Query param |
| utm_content | VARCHAR(255) | Yes | Query param |
| created_at | TIMESTAMP | Yes | Authoritative click time |
| updated_at | TIMESTAMP | Yes | Framework |

No `clicked_at`, `campaign_id`, `offer_id`, `user_id`, raw `ip_address`, `destination_url`, or denormalized click counter.

## Planned Classes

| Class | Type | File |
|---|---|---|
| `RedirectTrackingLinkController` | Invokable Controller | `app/Http/Controllers/` |
| `RecordTrackingClickAction` | Action | `app/Actions/TrackingLink/` |
| `TrackingClick` | Eloquent Model | `app/Models/` |
| `IpHasher` | Service | `app/Services/TrackingLink/` |
| `TrackingClickFactory` | Factory | `database/factories/` |

## Relationships

- `TrackingLink::clicks()` — HasMany(TrackingClick)
- `TrackingClick::trackingLink()` — BelongsTo(TrackingLink)

## Test Coverage Summary

Planned Pest coverage (34 scenarios) includes valid active Campaign redirect with click persistence, `302` status and Location header, `created_at` as click timestamp, click-to-TrackingLink binding, unknown code `404`, draft Campaign `404`, suspended Campaign `404`, inactive-vs-unknown `404` equivalence, no auth required, User-Agent normalization and storage, Referer normalization and storage, UTM normalization and storage, empty metadata becomes null, oversized metadata truncated safely, raw IP never stored, IP hash determinism, IPv6 equivalence, IP uniqueness, missing/invalid IP nullable, persistence failure reported, persistence failure still redirects, no error details exposed, unsafe schemes rejected, malformed URLs rejected, hostless URLs rejected, missing Campaign relation `404`, missing Offer relation `404`, no property-access exception, `TrackingLink::clicks()` works, `TrackingClick::trackingLink()` works, cascade deletion, bounded eager loading, KAN-14 unaffected. Postman/Newman validates the real public flow. Every scenario asserts database state and redirect behavior, not only status codes.

## Scope Exclusions

Conversion attribution, revenue calculations, dashboard analytics, unique visitor counting, bot detection, geolocation, device fingerprinting, cookies, link expiration, link deactivation, QR codes, campaign frontend, admin click management, AI features, batch analytics, retention policies, and queue-based click recording are explicitly excluded.

## Implementation Verification Commands

These commands are planned for use only after implementation approval:

```bash
php artisan test tests/Feature/TrackingRedirectTest.php
php artisan test
vendor/bin/pint --test
php artisan route:list --path=/t/
php artisan migrate:status
git diff --check
git status --short
git diff --stat
```

No migration execution or destructive database command is part of verification.

## Approval Points

The package makes these explicit product/API choices that may be changed before implementation:

1. Draft and suspended Campaigns return `404` (not `410 Gone` or status-revealing responses).
2. Click persistence failure is logged but does not block the redirect.
3. A purpose-separated derived key from `APP_KEY` is used for IP hashing (not `APP_KEY` directly).
4. `created_at` is the authoritative click timestamp (no `clicked_at` column).
5. `302 Found` is used instead of `307` or `301`.
6. Synchronous best-effort click recording is used instead of queued jobs.
7. Defense-in-depth destination URL validation requires FILTER_VALIDATE_URL + scheme + host.
8. Both `TrackingLink::clicks()` and `TrackingClick::trackingLink()` relationships are implemented.
9. Postman/Newman collection is part of KAN-15 scope.

Approval of this package authorizes planning decisions only unless implementation is separately requested.
