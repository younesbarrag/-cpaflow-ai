# Proposal - KAN-16: Enregistrer une conversion sans doublon

## 1. Summary

Add an authenticated API endpoint to record a conversion linked to a Campaign, with database-level duplicate prevention using the advertiser's external transaction identifier. The conversion snapshots the Offer payout as revenue at recording time and defaults to `pending` status.

## 2. Problem

KAN-14 generates tracking links and KAN-15 records clicks and redirects, but there is no mechanism to record conversions. CPAFlow needs to track when a visitor completes a conversion action. Duplicate conversions must be prevented at the database level, not merely at the application level, to guarantee correctness under concurrent requests.

## 3. Objectives

- Record a conversion linked to a Campaign via an authenticated API endpoint.
- Prevent duplicate conversions using a database UNIQUE constraint on `external_id`.
- Snapshot the Offer payout as `revenue` at conversion time for financial integrity.
- Default new conversions to `pending` status using the existing `ConversionStatus` enum.
- Return `409 Conflict` for duplicate `external_id` submissions.
- Verify ownership through `Campaign → Offer → User`.
- Cover behavior with Pest feature tests and a Postman/Newman collection.

## 4. In Scope

- Additive `conversions` migration.
- `Conversion` model with `ConversionStatus` enum cast.
- `Campaign::conversions()` and `Conversion::campaign()` relationships.
- `RecordConversionAction` for conversion persistence with revenue snapshotting.
- `DuplicateConversionException` domain exception.
- `ConversionController` for the authenticated store endpoint.
- `StoreConversionRequest` for validation and authorization.
- `ConversionResource` for API serialization.
- `CampaignPolicy::recordConversion` for ownership verification.
- Pest feature coverage, Postman/Newman collection, and technical documentation update.

## 5. Out of Scope

- Conversion dashboard.
- Conversion status transitions (approve/reject).
- Attribution analytics.
- Period filters and reporting.
- Campaign expenses.
- AI features.
- Conversion editing or deletion.
- Batch import.
- Frontend conversion UI.
- Public postback endpoint with secret/token authentication.
- Refunds and chargebacks.
- Fraud detection.
- Unique visitor counting.
- Tracking link or click attribution on conversions.
- Tracking link or click as prerequisite for conversion recording.

## 6. Dependencies and Compatibility

- Existing `users`, `offers`, `campaigns`, `tracking_links`, and `tracking_clicks` tables from KAN-8 through KAN-15.
- Existing `ConversionStatus` enum (`pending`, `approved`, `rejected`) created in KAN-8 but not yet linked to a model or migration.
- Existing `Campaign`, `Offer` models and their relationships.
- Existing `CampaignStatus` enum with `draft`, `active`, `suspended`.
- Existing ownership chain: `Campaign → Offer → User`.

The migration is additive. It creates only the `conversions` table and must not alter or drop existing data or schema.

## 7. Key Decisions

| Decision | Outcome |
|---|---|
| Endpoint location | `/api/v1/campaigns/{campaign}/conversions` under `auth:sanctum` |
| Route name | `api.v1.campaigns.conversions.store` |
| Conversion parent | `Campaign` (FK `campaign_id`) — derives Offer, User |
| Dedup key | `external_id` — NOT NULL, UNIQUE database constraint |
| Duplicate HTTP behavior | `409 Conflict` via `DuplicateConversionException` |
| Revenue snapshotting | Server-derived from `Offer.payout`, stored as `revenue` — client cannot submit |
| Default status | `pending` using existing `ConversionStatus` enum |
| `converted_at` | Server-generated `now()` — not accepted from client |
| `source` | Optional, nullable, informational only — accepted from client |
| `tracking_link_id` / `tracking_click_id` | Not in KAN-16 — tracking attribution is outside scope |
| Campaign status | No restriction — conversions may arrive for draft/suspended Campaigns |
| Concurrency | Database UNIQUE constraint is the final protection; `UniqueConstraintViolationException` caught and converted to `DuplicateConversionException` |

## 8. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Concurrent duplicate requests create two rows | Database UNIQUE constraint on `external_id` is the final protection |
| Client forges `revenue` value | Revenue is snapshotted server-side from Offer.payout; not accepted from request body |
| Client forges `campaign_id` to foreign Campaign | `CampaignPolicy::recordConversion` verifies ownership through Campaign → Offer → User |
| `external_id` NULL would bypass UNIQUE | `external_id` is NOT NULL — enforced at migration and validation level |
| `UniqueConstraintViolationException` for unrelated column | Action catches only the specific unique violation on `external_id` |
| Conversions for draft/suspended Campaigns | Allowed — a conversion can legitimately arrive after a TrackingLink click even if Campaign is later suspended |

## 9. Success Criteria

- Valid conversion with `external_id` for an owned Campaign is recorded with `201`.
- Conversion is linked to the correct Campaign.
- `revenue` is snapshotted from the Offer.payout.
- `status` defaults to `pending`.
- `converted_at` is server-generated as `now()`.
- Duplicate `external_id` returns `409 Conflict`.
- Database UNIQUE constraint prevents duplicates under concurrent requests.
- Unknown Campaign returns `404`.
- Foreign Campaign returns `403`.
- Guest request returns `401`.
- Client-submitted `revenue` is ignored (server-derived).
- Response follows the `data.conversion` envelope.
- Response contains `id`, `campaign_id`, `external_id`, `source`, `revenue`, `status`, `converted_at`, `created_at`, `updated_at`.
- Different `external_id` values create independent conversions.
- Relationships work: `Campaign::conversions()`, `Conversion::campaign()`.
- Cascade deletion works: deleting a Campaign removes its Conversions.
- KAN-14 generation remains unaffected.
- KAN-15 click/redirect remains unaffected.
- Postman/Newman collection validates the real flow.
- Full Pest suite remains green.

## 10. Approval Gate

This package is planning only. Production implementation, migration execution, dependency installation, staging, commits, pushes, and Jira updates require explicit approval.
