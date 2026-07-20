# Proposal: Manage Profile, Logout and Roles (KAN-10)

## Problem

KAN-9 implemented secure web and API authentication (registration, login, logout, token management). However, authenticated users cannot update their profile via the API, there is no administrator middleware to protect future admin routes, and the existing Breeze web profile update does not use a shared business-logic Action. KAN-10 closes these gaps.

## Objective

1. Expose an API endpoint for authenticated users to update their name and email.
2. Share profile-update business logic between the web ProfileController and the new API controller via a single Action.
3. Implement an administrator middleware (`admin`) that restricts access to `UserRole::Admin` users.
4. Verify that logout correctly revokes only the current Sanctum token.
5. Ensure the existing `UserRole` enum and role column are properly used and cannot be escalated through profile input.

## Current State

**Git Branch:** `feature/KAN-10-profile-roles` (clean working tree)

**Laravel:** 13.20.0 — PHP 8.3+

**Already implemented by KAN-9:**

| Feature | Evidence |
|---------|----------|
| Laravel Breeze Blade auth | `routes/auth.php`, auth views, web controllers |
| Laravel Sanctum token auth | `HasApiTokens` on User, `personal_access_tokens` migration |
| UserRole enum (affiliate, admin) | `app/Enums/UserRole.php` |
| role column on users | `database/migrations/..._add_role_to_users_table.php` |
| GET /api/v1/auth/user | `AuthController::user()` — returns `UserResource` |
| POST /api/v1/auth/logout | `AuthController::logout()` — revokes current token only |
| Web profile pages (Breeze) | `ProfileController::edit/update/destroy`, profile views |
| ProfileUpdateRequest (web) | Validates name, email (unique ignoring self) |
| Pest tests with SQLite in-memory | `phpunit.xml`, `tests/Pest.php` |
| API UserResource | Returns id, name, email, role — never password/token |
| API registration role escalation blocked | Test proves `role=admin` input has no effect |

**Missing / to implement:**

| Feature | Status |
|---------|--------|
| API profile update endpoint | Not implemented |
| Shared profile-update Action | Not implemented |
| API profile Form Request | Not implemented |
| Administrator middleware | Not implemented |
| Middleware alias (`admin`) registration | Not implemented |
| API profile tests | Not implemented |
| Admin middleware tests | Not implemented |

## Scope

### In Scope

1. **API profile update** — `PATCH /api/v1/profile` returning `UserResource` (HTTP 200).
2. **Shared UpdateUserProfileAction** — single business-logic class used by both web and API profile update.
3. **API profile Form Request** — `UpdateProfileRequest` with name, email (unique ignoring self), email normalization.
4. **Administrator middleware** — `EnsureUserIsAdmin`, registered as `admin` alias.
5. **Verification tests** — API profile, admin middleware, logout, role-security.
6. **Web ProfileController adaptation** — use `UpdateUserProfileAction` instead of inline logic.

### Exclusions (not in KAN-10 scope)

- Offers, campaigns, tracking, clicks, conversions, expenses
- Dashboard, statistics, AI
- Password change (existing Breeze scaffolding remains as-is)
- Account deletion redesign
- User suspension
- Admin user-management CRUD (KAN-22)
- Permissions management
- OAuth, social authentication, 2FA
- Docker, CI/CD, Azure deployment
- Database migrations (no new columns needed)

## Dependencies

- KAN-9: authentication, Sanctum, Breeze, User model, UserRole enum, role column
- KAN-8: API versioning, Health endpoint, Pest infrastructure

## Expected Impact

- Authenticated users can update their profile via API and web.
- Business logic for profile updates is centralized in a single Action.
- Administrator routes can be protected with the `admin` middleware.
- Existing KAN-9 tests continue to pass.
- No database migration required.
- No new Composer or npm dependency required.

## Risks

| Risk | Mitigation |
|------|------------|
| Web ProfileController change may break existing tests | Adapt incrementally; run full test suite after each change |
| Admin middleware could be accidentally applied to wrong routes | Register as explicit alias; test with dedicated test routes |
| Email uniqueness validation may reject the user's own email | `Rule::unique()->ignore($user->id)` pattern (already used in web ProfileUpdateRequest) |

## Acceptance Criteria

1. An authenticated user can consult their profile via API (GET /api/v1/auth/user).
2. An authenticated user can update their name and email via API (PATCH /api/v1/profile).
3. API logout revokes only the current Sanctum token.
4. UserRole enum represents Affiliate and Admin.
5. Admin middleware protects administrator routes (Affiliate gets 403, unauthenticated gets 401).
