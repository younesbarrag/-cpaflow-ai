# KAN-16 - Record Conversion Without Duplicate

## Status

Planning complete. Awaiting approval; production implementation has not started.

## Story

As an Affiliate, I want to record a conversion without duplicates so I can track advertiser postback transactions.

Branch: `feature/KAN-16-record-conversion`

## Package

- `proposal.md`: problem, objectives, scope, risks, and approval gate.
- `design.md`: exact schema, duplicate prevention, revenue snapshotting, endpoint, Action, Form Request, Resource, Policy, exception handling, and documentation design.
- `spec.md`: normative requirements, scenarios, HTTP behavior, and acceptance mapping.
- `tasks.md`: 49 independently verifiable, unchecked implementation tasks.
- `README.md`: planning summary and key decisions.

## Key Decisions

| Topic | Decision |
|---|---|
| Endpoint | `POST /api/v1/campaigns/{campaign}/conversions` under `auth:sanctum` |
| Route name | `api.v1.campaigns.conversions.store` |
| Conversion parent | `Campaign` (FK `campaign_id`) — derives Offer, User per MCD |
| Dedup key | `external_id` — NOT NULL, UNIQUE database constraint |
| Duplicate behavior | `409 Conflict` via `DuplicateConversionException` |
| Revenue | Snapshotted from `Offer.payout` — client cannot submit |
| Default status | `pending` using existing `ConversionStatus` enum |
| `converted_at` | Server-generated `now()` — not accepted from client |
| `source` | Optional, nullable, informational — accepted from client |
| `tracking_link_id` | Not in KAN-16 — tracking attribution is outside scope |
| Campaign status | No restriction — conversions allowed for any Campaign status |
| Form Request | No `unique` validation rule on `external_id` — DB UNIQUE only |
| Concurrency | Database UNIQUE constraint is the final protection |

## Planned Endpoint

| Method | URI | Route name |
|---|---|---|
| POST | `/api/v1/campaigns/{campaign}/conversions` | `api.v1.campaigns.conversions.store` |

Route is under `/api/v1` and protected by `auth:sanctum`.

## Planned Schema

`conversions` table:

| Column | Type | Nullable | Notes |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | auto increment |
| campaign_id | BIGINT UNSIGNED FK | No | CASCADE on delete |
| external_id | VARCHAR(255) | No | UNIQUE — dedup key |
| source | VARCHAR(255) | Yes | Informational |
| revenue | DECIMAL(12,2) | No | DEFAULT 0.00, snapshotted from Offer.payout |
| status | VARCHAR(20) | No | DEFAULT 'pending', INDEX |
| converted_at | TIMESTAMP | No | DEFAULT CURRENT_TIMESTAMP, server-generated |
| created_at | TIMESTAMP | Yes | Framework-managed |
| updated_at | TIMESTAMP | Yes | Framework-managed |

No `tracking_link_id`, `tracking_click_id`, `offer_id`, `user_id`, `payout`, raw `ip_address`, `conversion_count`, or soft deletes.

`external_id` NOT NULL intentionally refines the older nullable MLD because KAN-16 requires deterministic duplicate prevention.

## Planned Classes

| Class | Type | File |
|---|---|---|
| `ConversionController` | Controller | `app/Http/Controllers/Api/V1/` |
| `RecordConversionAction` | Action | `app/Actions/Conversion/` |
| `Conversion` | Eloquent Model | `app/Models/` |
| `StoreConversionRequest` | Form Request | `app/Http/Requests/Api/V1/Conversion/` |
| `ConversionResource` | API Resource | `app/Http/Resources/Api/V1/` |
| `CampaignPolicy::recordConversion` | Policy method | `app/Policies/CampaignPolicy.php` |
| `DuplicateConversionException` | Domain Exception | `app/Exceptions/` |
| `ConversionFactory` | Factory | `database/factories/` |

No `ConversionPolicy` — authorization is added to existing `CampaignPolicy`.

## Relationships

- `Campaign::conversions()` — HasMany(Conversion)
- `Conversion::campaign()` — BelongsTo(Campaign)

## Test Coverage Summary

Planned Pest coverage (20 scenarios + Postman) includes valid conversion recording with revenue snapshotting, `201` status and `data.conversion` envelope, duplicate `external_id` returns `409`, database uniqueness enforcement, concurrent duplicate safety, different `external_id` creates new conversion, unknown Campaign `404`, missing `external_id` `422`, foreign Campaign `403`, guest `401`, `status` defaults to `pending`, `converted_at` server-generated, `source` accepted/nullable, relationships work, cascade deletion works, KAN-14 unaffected, KAN-15 unaffected. Postman/Newman validates the real API flow. Every scenario asserts database state and response behavior.

## Scope Exclusions

Conversion dashboard, conversion status transitions, attribution analytics, period filters, campaign expenses, AI features, conversion editing, conversion deletion, batch import, frontend conversion UI, public postback endpoint, refunds, chargebacks, fraud detection, unique visitor counting, tracking link/click attribution, and tracking link/click as prerequisite are explicitly excluded.

## Implementation Verification Commands

These commands are planned for use only after implementation approval:

```bash
php artisan test tests/Feature/Api/V1/ConversionApiTest.php
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api/v1/campaigns/{campaign}/conversions
php artisan migrate:status
git diff --check
git status --short
git diff --stat
```

No migration execution or destructive database command is part of verification.

## Approval Points

The package makes these explicit product/API choices that may be changed before implementation:

1. The endpoint uses `auth:sanctum` — the affiliate records conversions through the API (not a public postback).
2. Conversion belongs to Campaign per MCD — not to TrackingLink.
3. `external_id` is NOT NULL — every conversion requires an advertiser transaction ID.
4. Duplicate `external_id` returns `409 Conflict` (not idempotent `200`).
5. `revenue` is snapshotted from Offer.payout — the client cannot submit a custom revenue value.
6. `source` is optional and nullable — informational only.
7. `converted_at` is server-generated — not accepted from the client.
8. No Campaign status check — conversions may arrive for draft/suspended Campaigns.
9. No `unique` validation rule on `external_id` in the Form Request — uniqueness is DB-level only.
10. No `tracking_link_id` or `tracking_click_id` — tracking attribution is outside KAN-16 scope.

Approval of this package authorizes planning decisions only unless implementation is separately requested.
