# KAN-11 — Create and List CPA Offers

## Summary

Introduces the core CPA offers entity to CPAFlow AI:
- Database migration for `offers` table with foreign key to `users`
- `Offer` model with `belongsTo` User relationship
- `POST /api/v1/offers` — authenticated offer creation (HTTP 201)
- `GET /api/v1/offers` — paginated listing of authenticated user's offers (HTTP 200)
- Shared `CreateOfferAction` for business logic
- `OfferResource` for consistent JSON responses
- Comprehensive Pest tests for authentication, validation, ownership, and pagination

## Status

**Planning complete** — awaiting approval before implementation.

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Foreign key `ON DELETE CASCADE` | User deletion should cascade to offers; no orphaned data; Breeze `destroy()` flow is unaffected |
| Status-based archival (no SoftDeletes) | `OfferStatus::Archived` is sufficient; avoids dual deletion mechanisms; KAN-12 builds on this |
| `DECIMAL(12,2)` for payout | Exact precision; no float corruption; sufficient range for CPA payouts |
| `VARCHAR(2048)` for destination_url | Accommodates long URLs with query params; more efficient than TEXT |
| No `GET /api/v1/offers/{offer}` in KAN-11 | Jira criteria only require create + list; detail endpoint needs per-offer auth (KAN-12) |
| No OfferPolicy in KAN-11 | All operations inherently scoped via `$request->user()->offers()`; Policy deferred to KAN-12 |
| No `ListUserOffersAction` | Listing query is a single Eloquent chain; over-engineering to extract |
| `user_id` NOT in `$fillable` | Ownership set via relationship; prevents mass-assignment vulnerability |
| `payout` cast to `decimal:2` | Returns string, never float; prevents precision loss |
| Default page size 15 | Matches Laravel default; configurable pagination deferred to when frontend needs it |

## Planned Files

### Create

| File | Purpose |
|------|---------|
| `database/migrations/..._create_offers_table.php` | Offers table migration |
| `app/Models/Offer.php` | Offer Eloquent model |
| `app/Actions/Offer/CreateOfferAction.php` | Offer creation business logic |
| `app/Http/Controllers/Api/V1/OfferController.php` | API controller (store, index) |
| `app/Http/Requests/Api/V1/Offer/StoreOfferRequest.php` | Creation validation |
| `app/Http/Resources/Api/V1/OfferResource.php` | JSON response structure |
| `database/factories/OfferFactory.php` | Test data generation |
| `tests/Feature/Api/V1/OfferApiTest.php` | API behavior tests |

### Modify

| File | Change |
|------|--------|
| `app/Models/User.php` | Add `offers(): HasMany` relationship |
| `routes/api.php` | Add `GET /offers` and `POST /offers` routes |
| `docs/conception-technique.md` | Mark offers as implemented |

## API Endpoints

| Method | Endpoint | Auth | Status | Purpose |
|--------|----------|------|--------|---------|
| `POST` | `/api/v1/offers` | `auth:sanctum` | 201 | Create offer |
| `GET` | `/api/v1/offers` | `auth:sanctum` | 200 | List user's offers (paginated) |

## Verification Commands

```bash
# Run all tests
php artisan test

# Check code style
./vendor/bin/pint --test

# Build frontend
npm run build

# List routes
php artisan route:list --path=api/v1

# Migration status
php artisan migrate:status
```

## Jira Acceptance Criteria Mapping

| Criterion | Requirement | Status |
|-----------|-------------|--------|
| 1. User must be authenticated | R1.3, R5.5 | To implement |
| 2. Offer name, destination_url, payout, status validated | R2.1–R2.12 | To implement |
| 3. Offer automatically linked to authenticated user | R1.2, R3.1, R3.2 | To implement |
| 4. Creation returns JSON HTTP 201 | R1.1, R4.1 | To implement |
| 5. List is paginated and contains only authenticated user's offers | R5.1–R5.4 | To implement |
