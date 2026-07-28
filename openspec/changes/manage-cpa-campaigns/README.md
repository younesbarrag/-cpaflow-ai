# KAN-13 - Manage CPA Campaigns

## Status

Planning complete. Awaiting approval; production implementation has not started.

## Story

As an Affiliate, I want to create and manage a campaign associated with one of my offers so I can track its promotion.

Branch: `feature/KAN-13-cpa-campaign-management`

## Package

- `proposal.md`: problem, objectives, scope, risks, and approval gate.
- `design.md`: exact schema, ownership, lifecycle, authorization, validation, Actions, routes, pagination, Resource, and documentation design.
- `spec.md`: normative requirements, scenarios, HTTP behavior, and acceptance mapping.
- `tasks.md`: 46 independently verifiable, unchecked implementation tasks.
- `README.md`: planning summary and key decisions.

## Key Decisions

| Topic | Decision |
|---|---|
| Campaign owner | Derived from `Campaign -> Offer -> User`; no direct `user_id` |
| Schema | `offer_id`, name, traffic source, `DECIMAL(12,2)` budget, status, timestamps |
| Parent FK | Indexed `offer_id`, `ON DELETE CASCADE` |
| Statuses | `draft`, `active`, `suspended` only |
| Initial status | `CreateCampaignAction` always sets draft; any submitted Store status returns `422` |
| Lifecycle | Dedicated activate/suspend POST operations; status prohibited in PATCH |
| Repeated lifecycle action | `409 Conflict`, no write, non-idempotent |
| Traffic source | Trimmed free-form string up to 255 characters |
| Archived owned Offer | `422` with `offer_id` validation error |
| Foreign existing resource | `403`; missing resource is `404` |
| Pagination | User-scoped, `id DESC`, fixed 15, existing `data`/`links`/`meta` format |
| Filters | None; status filtering and search are outside KAN-13 |

## Planned Endpoints

| Method | URI | Route name |
|---|---|---|
| GET | `/api/v1/campaigns` | `api.v1.campaigns.index` |
| POST | `/api/v1/campaigns` | `api.v1.campaigns.store` |
| GET | `/api/v1/campaigns/{campaign}` | `api.v1.campaigns.show` |
| PATCH | `/api/v1/campaigns/{campaign}` | `api.v1.campaigns.update` |
| POST | `/api/v1/campaigns/{campaign}/activate` | `api.v1.campaigns.activate` |
| POST | `/api/v1/campaigns/{campaign}/suspend` | `api.v1.campaigns.suspend` |

All routes are under `/api/v1` and protected by `auth:sanctum`.

## Planned Resource

CampaignResource returns:

- `id`
- `offer: { id, name }`
- `name`
- `traffic_source`
- `budget` as a two-decimal string
- `status`
- `created_at`
- `updated_at`

## Test Coverage Summary

Planned Pest coverage includes creation/authentication, `422` for any submitted Store status, missing/foreign/archived Offers, all field validation, ownership isolation, deterministic pagination metadata, eager-loaded Offer context without lazy loading, show authorization and missing IDs, true PATCH semantics, protected fields both alone and alongside valid fields, every allowed transition, repeated activate/suspend `409` with no database write, and guest/foreign lifecycle denial. Every mutation or failure scenario asserts database state, not only status codes.

## Scope Exclusions

Tracking links, clicks, conversions, analytics, attribution, campaign deletion, campaign archival, AI features, dashboards, frontend work, and Admin campaign management are explicitly excluded.

## Implementation Verification Commands

These commands are planned for use only after implementation approval:

```bash
php artisan test tests/Feature/Api/V1/CampaignApiTest.php
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api/v1/campaigns
php artisan migrate:status
git diff --check
git status --short
git diff --stat
```

No migration execution or destructive database command is part of verification.

## Approval Points

The package makes two explicit product/API choices that may be changed before implementation:

1. An owned archived Offer produces `422`, while lifecycle conflicts produce `409`.
2. Activate/suspend operations are strict and non-idempotent; repeating an already-achieved transition produces `409`.

Approval of this package authorizes planning decisions only unless implementation is separately requested.
