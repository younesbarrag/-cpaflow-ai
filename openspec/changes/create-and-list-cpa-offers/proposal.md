# Proposal: Create and List CPA Offers (KAN-11)

## Problem

KAN-8, KAN-9, and KAN-10 established the Laravel foundation, authentication, and profile management. The application can authenticate users and manage profiles, but there is no concept of CPA offers yet. Affiliates need to create and manage offers — the core business entity of the platform — before they can create campaigns, tracking links, or record conversions. KAN-11 introduces the offers table, model, and API endpoints for creation and paginated listing.

## Objective

1. Create a database migration for the `offers` table with a foreign key to `users`.
2. Implement the `Offer` model with proper casts, mass-assignment protection, and a `belongsTo` relationship to `User`.
3. Add a `User::offers()` `HasMany` relationship.
4. Create a `CreateOfferAction` to centralize offer-creation business logic.
5. Expose `POST /api/v1/offers` for authenticated offer creation (HTTP 201).
6. Expose `GET /api/v1/offers` for paginated listing of the authenticated user's offers (HTTP 200).
7. Validate all input through dedicated Form Requests.
8. Return consistent JSON responses via `OfferResource`.
9. Create an `OfferFactory` for test data.
10. Write comprehensive Pest tests covering authentication, validation, ownership, pagination, and regression.

## Current State

**Git Branch:** `feature/KAN-11-offers-create-list`

**Laravel:** 13.20.0 — PHP 8.4.20

**Already implemented by KAN-8, KAN-9, KAN-10:**

| Feature | Evidence |
|---------|----------|
| Laravel 13 + Sanctum | `composer.json`, `HasApiTokens` on User |
| `auth:sanctum` middleware | `routes/api.php` — authenticated route group |
| `UserRole` enum | `app/Enums/UserRole.php` — Affiliate, Admin |
| `OfferStatus` enum | `app/Enums/OfferStatus.php` — Draft, Active, Suspended, Archived |
| `CampaignStatus` enum | `app/Enums/CampaignStatus.php` |
| `ConversionStatus` enum | `app/Enums/ConversionStatus.php` |
| `AiProcessStatus` enum | `app/Enums/AiProcessStatus.php` |
| API versioning `/api/v1` | `routes/api.php` — prefix `v1` |
| `UserResource` | `app/Http/Resources/Api/V1/UserResource.php` |
| Auth actions | `RegisterUserAction`, `AuthenticateApiUserAction` |
| Profile action | `UpdateUserProfileAction` |
| Admin middleware | `app/Http/Middleware/EnsureUserIsAdmin.php` |
| Form Requests | `RegisterApiRequest`, `LoginApiRequest`, `UpdateProfileRequest` |
| Pest + SQLite in-memory | `phpunit.xml`, `tests/Pest.php` |
| User model | `app/Models/User.php` — `#[Fillable(['name', 'email', 'password'])]` |
| MLD offers table | `docs/conception-technique.md` — planned schema |

**Missing / to implement:**

| Feature | Status |
|---------|--------|
| `offers` database table | Not created |
| `Offer` model | Not created |
| `User::offers()` relationship | Not created |
| `Offer::user()` relationship | Not created |
| `CreateOfferAction` | Not created |
| `OfferController` (API) | Not created |
| `StoreOfferRequest` | Not created |
| `OfferResource` | Not created |
| `OfferFactory` | Not created |
| Offer API routes | Not created |
| Offer API tests | Not created |

## Scope

### In Scope

1. **Database migration** — `offers` table with foreign key to `users`, indexes, default status.
2. **Offer model** — casts, mass-assignment, `belongsTo` User relationship.
3. **User model extension** — `offers()` `HasMany` relationship.
4. **CreateOfferAction** — receives authenticated User and trusted fields, creates and associates Offer.
5. **StoreOfferRequest** — validates name, destination_url, payout, status, description.
6. **OfferController** — `store()` (create, HTTP 201) and `index()` (paginated list, HTTP 200).
7. **OfferResource** — consistent JSON response structure.
8. **OfferFactory** — test data generation with User relationship.
9. **Pest tests** — authentication, creation, validation, ownership, pagination, regression.
10. **Documentation update** — `docs/conception-technique.md` marks offers as implemented.

### Exclusions (not in KAN-11 scope)

- Offer update (KAN-12)
- Offer deletion (KAN-12)
- Offer archival (KAN-12)
- Filtering by status
- Searching by name
- Viewing another user's offer
- Viewing a single offer detail (`GET /api/v1/offers/{offer}`)
- Campaigns
- Tracking links
- Clicks
- Conversions
- Expenses
- Dashboard
- AI
- SoftDeletes on offers
- OfferPolicy (deferred to KAN-12)
- Jobs or Queues
- Docker, CI/CD, Azure deployment

## Dependencies

- KAN-8: Foundation, OfferStatus enum, API versioning
- KAN-9: Authentication, Sanctum, User model
- KAN-10: Profile management, admin middleware

## Expected Impact

- Authenticated users can create CPA offers via API.
- Authenticated users can list their offers via paginated API.
- The `offers` table is created in MySQL.
- Offer business logic is centralized in a dedicated Action.
- Existing KAN-8/9/10 tests continue to pass.
- No Composer or npm dependency changes.

## Risks

| Risk | Mitigation |
|------|------------|
| Foreign key delete behavior could block user deletion | Evaluate cascade vs. restrict; document decision |
| Payout precision loss with float casting | Use `decimal:2` cast, never float |
| Mass-assignment vulnerability on `user_id` | Do not include `user_id` in `#[Fillable]`; create through relationship |
| Pagination performance on large datasets | Use offset-based pagination with index on `user_id` |
| Status default handling inconsistency | Default to `draft` in migration; validate via enum in Form Request |

## Acceptance Criteria

1. An authenticated user can create an offer via `POST /api/v1/offers`.
2. Offer name, destination_url, payout, and status are validated.
3. The offer is automatically linked to the authenticated user.
4. Creation returns HTTP 201 with OfferResource.
5. `GET /api/v1/offers` returns a paginated list containing only the authenticated user's offers.
6. Unauthenticated requests return HTTP 401.
7. Invalid input returns HTTP 422 with validation errors.
