# Proposal — KAN-12: Modifier, filtrer et archiver ses offres CPA

## Problem Statement

KAN-11 established the core CPA offers entity with creation and listing. However, affiliates currently cannot:

- Update offer details after creation (name, URL, payout, status, description)
- Archive offers they no longer wish to promote
- Filter offers by status for better organization
- Search offers by name for quick retrieval

Without these capabilities, affiliates must delete and recreate offers to make changes, losing historical data and audit trails.

## Objectives

1. Allow authenticated users to update their own offers via `PATCH /api/v1/offers/{offer}`
2. Allow authenticated users to archive their own offers via `POST /api/v1/offers/{offer}/archive`
3. Extend the listing endpoint to support `status` and `search` query parameters
4. Enforce strict ownership authorization — only the offer owner can update or archive
5. Ensure foreign-user access returns HTTP 403 (not 404)

## Scope

### In Scope

- `OfferPolicy` for authorization (update, archive)
- `UpdateOfferAction` for update business logic
- `ArchiveOfferAction` for archive business logic
- `UpdateOfferRequest` for update validation
- `IndexOfferRequest` for filter/search validation
- Eloquent scopes for status filtering and name search
- Controller methods: `index()` (extended), `update()`, `archive()`
- Comprehensive Pest tests

### Out of Scope

- Physical offer deletion
- SoftDeletes / restore
- Campaign management
- Tracking links, clicks, conversions
- Dashboard, analytics
- AI features
- Admin offer management
- Bulk operations

## Dependencies

| Dependency | Status | Impact |
|------------|--------|--------|
| KAN-11 — Create and List CPA Offers | Implemented | Provides `Offer` model, migration, factory, listing endpoint |
| `OfferStatus` enum | Implemented | Provides `Archived` status value |
| `offers` table migration | Implemented | Provides `status` column, `user_id/status` composite index |
| Sanctum auth:sanctum | Implemented | Provides authentication middleware |

## Success Criteria

- Owner can update offer fields (partial updates supported)
- Owner can archive an offer (status becomes `archived`)
- Non-owner receives HTTP 403 for update and archive
- Guest receives HTTP 401 for all protected endpoints
- Listing supports `?status=active`, `?search=fitness`, `?status=draft&search=fitness`
- Filters never expose another user's offers
- All existing KAN-11 tests continue to pass
- No new migrations required
