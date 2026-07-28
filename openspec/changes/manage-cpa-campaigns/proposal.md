# Proposal - KAN-13: Creer et gerer les campagnes CPA

## 1. Summary

Add authenticated campaign creation and management to the CPAFlow AI Laravel API. A campaign belongs to one existing offer, and ownership is derived from that offer. The change provides create, list, show, partial update, activate, and suspend operations without adding tracking or analytics behavior.

## 2. Problem

Affiliates can currently create and manage offers, but cannot represent the promotion of an offer as a campaign. KAN-13 needs a campaign boundary that preserves offer ownership, rejects archived offers at creation, validates campaign data, and protects status transitions.

## 3. Objectives

- Persist campaigns under existing offers with no duplicated ownership column.
- Restrict every operation to the authenticated owner of the parent offer.
- Reject campaign creation for an owned archived offer.
- Validate name, traffic source, budget, and status semantics.
- Provide deterministic, user-isolated pagination using the existing API envelope.
- Keep controllers thin by using Form Requests, Policies, Actions, and API Resources.
- Cover behavior and persisted state with Pest feature tests.
- Update `docs/conception-technique.md` only when KAN-13 is implemented.

## 4. In Scope

- Additive `campaigns` migration.
- `CampaignStatus` enum with `draft`, `active`, and `suspended` only.
- `Campaign` model, factory, and Eloquent relationships.
- Campaign authorization and parent-offer creation authorization.
- Store and update Form Requests.
- Create, update, activate, and suspend Actions.
- Campaign API Resource, controller, and six `/api/v1` routes.
- Fixed 15-item pagination, ordered by descending campaign ID.
- Pest feature coverage and final technical documentation.

## 5. Out of Scope

- Tracking links.
- Clicks and conversions.
- Analytics and attribution.
- Campaign deletion or archival.
- AI features.
- Dashboards.
- Frontend implementation.
- Admin campaign management or an Admin authorization bypass.
- Offer reassignment after campaign creation.
- Search, status filters, configurable page size, and sorting controls.

## 6. Dependencies and Compatibility

- Existing `users` and `offers` tables.
- Existing `Offer`, `OfferPolicy`, `OfferResource`, and `OfferStatus` implementation from KAN-11/KAN-12.
- Existing Sanctum-protected `/api/v1` route group.
- Laravel 13 policy auto-discovery and API Resource pagination behavior.
- Pest 4 feature-test conventions with `RefreshDatabase`.

The migration is additive. It creates only the `campaigns` table and must not alter or drop existing data or schema.

## 7. Key Decisions

| Decision | Outcome |
|---|---|
| Ownership | Derived only through `Campaign -> Offer -> User`; no `campaigns.user_id` |
| Parent deletion | `campaigns.offer_id` cascades on offer deletion |
| Budget | `DECIMAL(12,2)`, non-null, no default, serialized as a two-decimal string |
| Initial status | `CreateCampaignAction` always sets `CampaignStatus::Draft`; any client-submitted Store `status` returns `422` |
| Status mutation | General PATCH cannot change status; dedicated activate/suspend operations enforce transitions |
| Repeated actions | Non-idempotent: repeated activate/suspend requests return `409 Conflict` and do not write |
| Traffic source | Trimmed free-form string, maximum 255 characters; no unsupported channel enum |
| Archived offer | Owned archived offer returns `422 Unprocessable Content` with an `offer_id` error |
| Listing filters | None in KAN-13; status filtering is not required to manage or paginate campaigns |

## 8. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Foreign offer data leaks during creation | Resolve the offer globally, authorize ownership before business validation, return `403` for a foreign existing offer |
| Ownership drift | Store only `offer_id`; prohibit reassignment and derive authorization through the relationship |
| Invalid lifecycle changes | Keep status out of general PATCH and enforce a strict transition matrix in Actions |
| Monetary precision loss | Use `DECIMAL(12,2)`, `decimal:2` cast, decimal validation, and string serialization |
| N+1 resource serialization | Eager-load the parent offer for list/show/mutation responses |
| Unindexed user listing | Query through a join/`whereHas` on indexed `offers.user_id`, with indexed `campaigns.offer_id` |

## 9. Success Criteria

- All six authenticated endpoints satisfy the HTTP contracts in `spec.md`.
- Existing foreign resources return `403`; missing resources return `404`.
- Campaign listing cannot include campaigns attached to another user's offers.
- Status and ownership fields cannot be bypassed through PATCH.
- Invalid and repeated lifecycle operations return `409` without changing persisted data.
- New campaign tests and the full existing suite pass.
- Formatting, route inspection, and non-destructive migration status checks pass.

## 10. Approval Gate

This package is planning only. Production implementation, migration execution, dependency installation, staging, commits, pushes, and Jira updates require explicit approval.
