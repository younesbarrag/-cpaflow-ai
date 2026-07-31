# KAN-14 - Generate Campaign Tracking Link

## Status

Planning complete. Awaiting approval; production implementation has not started.

## Story

As an Affiliate, I want to generate a unique tracking link for a campaign so I can measure visits to my Offer.

Branch: `feature/KAN-14-generate-tracking-link`

## Package

- `proposal.md`: problem, objectives, scope, risks, and approval gate.
- `design.md`: exact schema, ownership, code strategy, authorization, Form Request, Action, route, Resource, and documentation design.
- `spec.md`: normative requirements, scenarios, HTTP behavior, and acceptance mapping.
- `tasks.md`: 28 independently verifiable, unchecked implementation tasks.
- `README.md`: planning summary and key decisions.

## Key Decisions

| Topic | Decision |
|---|---|
| Link owner | Derived from `TrackingLink → Campaign → Offer → User`; no direct `user_id` |
| Schema | `id`, `campaign_id`, `code` (VARCHAR 32, UNIQUE), `created_at`, `updated_at` |
| Parent FK | Indexed `campaign_id`, `ON DELETE CASCADE` |
| Code generation | `Str::random(32)` — 32-character URL-safe alphanumeric |
| Code uniqueness | Database UNIQUE constraint + bounded retry (max 5) on verified unique violation only |
| Unrelated exceptions | Rethrown immediately, never retried |
| One vs. multiple links | A Campaign may have multiple tracking links |
| Draft/suspended Campaign | `422` with `errors.status` — "Only an active campaign can generate tracking links." |
| Foreign existing Campaign | `403`; missing Campaign is `404` |
| Authorization ordering | Form Request `authorize()` before `after()` status validation; foreign Campaign always `403` |
| URL construction | `url('/t/' . $code)` via Laravel URL generator |
| Transaction | No transaction; each attempt is one atomic INSERT |
| `is_active` | Removed — KAN-14 has no deactivation, rotation, or archival |

## Planned Endpoint

| Method | URI | Route name |
|---|---|---|
| POST | `/api/v1/campaigns/{campaign}/tracking-links` | `api.v1.campaigns.tracking-links.store` |

Route is under `/api/v1` and protected by `auth:sanctum`.

## Planned Resource

TrackingLinkResource returns exactly:

- `id`
- `campaign_id`
- `code`
- `url` (generated with `url('/t/' . $code)`)
- `created_at`
- `updated_at`

## Test Coverage Summary

Planned Pest coverage includes generation under an active owned Campaign, code uniqueness and URL-safety, database UNIQUE constraint effectiveness, `201` response with `data.tracking_link` envelope, guest `401`, foreign Campaign `403`, foreign inactive Campaign `403` before status validation, missing Campaign `404`, draft Campaign `422` with `errors.status`, suspended Campaign `422` with `errors.status`, no row persisted on rejection, repeated generation behavior, codes unique across generations, deterministic collision retry, unrelated database exception rethrown, collision exhaustion domain failure, response shape without ownership or Campaign field leaks, and persisted database state assertions. Every scenario asserts database state, not only status codes.

## Scope Exclusions

Public redirect endpoint, click recording, visit analytics, conversion attribution, IP address collection, user-agent collection, geolocation, link expiration, QR code generation, link rotation, `is_active` column, link deactivation, link deletion, multiple-link management, dashboards, AI features, frontend work, and Admin tracking-link management are explicitly excluded.

## Implementation Verification Commands

These commands are planned for use only after implementation approval:

```bash
php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api/v1/campaigns/{campaign}/tracking-links
php artisan migrate:status
git diff --check
git status --short
git diff --stat
```

No migration execution or destructive database command is part of verification.

## Approval Points

The package makes these explicit product/API choices that may be changed before implementation:

1. An owned draft or suspended Campaign produces `422` with `errors.status`, while a foreign Campaign always produces `403`.
2. Multiple tracking links are allowed per Campaign; there is no singleton constraint.
3. The endpoint is non-idempotent; repeated calls create new links.
4. The URL construction uses `url('/t/' . $code)` and does not define a named route because the redirect endpoint is out of scope.
5. `is_active` is not part of the KAN-14 schema; tracking-link usability will depend on Campaign status in KAN-15.

Approval of this package authorizes planning decisions only unless implementation is separately requested.
