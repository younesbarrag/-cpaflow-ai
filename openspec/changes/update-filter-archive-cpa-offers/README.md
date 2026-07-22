# KAN-12 — Modifier, filtrer et archiver ses offres CPA

## Summary

Extends the CPA offers CRUD with update, archive, filtering, and search capabilities:

- `PATCH /api/v1/offers/{offer}` — partial offer update (HTTP 200)
- `POST /api/v1/offers/{offer}/archive` — archive an offer (HTTP 200)
- `GET /api/v1/offers?status={status}&search={search}` — extended listing with filters
- `OfferPolicy` for ownership authorization
- Comprehensive Pest tests for authorization, validation, archiving, and filtering

## Status

**Planning complete** — awaiting approval before implementation.

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Status-based archival (no SoftDeletes) | Confirmed from KAN-11; `OfferStatus::Archived` is sufficient; no dual deletion mechanisms |
| Archival is idempotent | Re-archiving an already-archived offer returns 200; no error; no database write |
| POST for archive endpoint | Archival is a state transition action, not a partial update; POST is semantically correct |
| Policy auto-discovery | Laravel 13 auto-discovers policies in `App\Policies` — no explicit registration needed |
| Foreign offer returns 403 (not 404) | Route model binding resolves existing offers; Policy denies access → 403 |
| Partial updates via `sometimes` | All UpdateOfferRequest fields use `sometimes`; only submitted fields validated |
| No new migration | KAN-11 already provides status column, Archived enum value, and composite index |
| Eloquent scopes for filtering | `scopeStatus()` and `scopeSearch()` on Offer model; clean, testable, chainable |

## Jira Acceptance Criteria Mapping

| Criterion | Requirement | Design Decision | Task | Test |
|-----------|-------------|-----------------|------|------|
| 1. Only owner can update or archive | R1.1, R1.2 | OfferPolicy with ownership check | T1.1, T5.1, T5.2 | T7.1, T7.3 |
| 2. Status values use OfferStatus | R2.1 | Enum validation in FormRequest | T3.1 | T7.2 |
| 3. Archival uses coherent strategy | R2.2–R2.5 | Status-based, idempotent | T2.2, T5.2 | T7.3 |
| 4. List filterable by status | R4.1, R4.4 | Eloquent scope + IndexOfferRequest | T3.2, T4.1, T5.3 | T7.4 |
| 5. List searchable by name | R4.2, R4.3 | Eloquent scope + LIKE query | T3.2, T4.2, T5.3 | T7.4 |
| 6. Foreign offer returns 403 | R1.3 | Policy authorization after model binding | T1.1, T5.1, T5.2 | T7.5 |

## Files

### Create

| File | Purpose |
|------|---------|
| `app/Policies/OfferPolicy.php` | Authorization for update and archive |
| `app/Actions/Offer/UpdateOfferAction.php` | Update business logic |
| `app/Actions/Offer/ArchiveOfferAction.php` | Archive business logic |
| `app/Http/Requests/Api/V1/Offer/UpdateOfferRequest.php` | Update validation |
| `app/Http/Requests/Api/V1/Offer/IndexOfferRequest.php` | Filter/search validation |
| `tests/Feature/Api/V1/OfferManagementApiTest.php` | Comprehensive tests |

### Modify

| File | Change |
|------|--------|
| `app/Models/Offer.php` | Add `scopeStatus()` and `scopeSearch()` |
| `app/Http/Controllers/Api/V1/OfferController.php` | Add `update()`, `archive()`, extend `index()` |
| `routes/api.php` | Add PATCH and POST routes |
| `docs/conception-technique.md` | Update status for KAN-12 |

## API Endpoints

| Method | Endpoint | Auth | Status | Purpose |
|--------|----------|------|--------|---------|
| `PATCH` | `/api/v1/offers/{offer}` | `auth:sanctum` | 200 | Partial offer update |
| `POST` | `/api/v1/offers/{offer}/archive` | `auth:sanctum` | 200 | Archive an offer |
| `GET` | `/api/v1/offers?status=&search=` | `auth:sanctum` | 200 | List with filters (extended) |

## Verification Commands

```bash
# Run all tests
php artisan test

# Check code style
./vendor/bin/pint --test

# List routes
php artisan route:list --path=api/v1

# Migration status (should show no new migrations)
php artisan migrate:status
```

## Exclusions

- Physical offer deletion
- SoftDeletes / restore
- Campaign management
- Tracking links, clicks, conversions
- Dashboard, analytics
- AI features
- Admin offer management
- Bulk operations
