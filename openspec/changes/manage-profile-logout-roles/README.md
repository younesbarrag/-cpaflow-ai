# KAN-10 — Manage Profile, Logout and Roles

## Summary

Closes the gaps between KAN-9 authentication and full profile/role management:
- API profile update endpoint
- Shared profile-update business logic (web + API)
- Administrator middleware for future admin routes
- Verification tests for profile, logout, role security

## Status

**Planning complete** — awaiting approval before implementation.

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Reuse `GET /api/v1/auth/user` for profile consultation | Already returns UserResource; no duplicate needed |
| `PATCH /api/v1/profile` for profile update | PATCH allows partial updates; distinct from /auth prefix |
| Shared `UpdateUserProfileAction` | Single source of truth for email normalization and email_verified_at handling |
| No `MustVerifyEmail` in KAN-10 | Existing behavior is sufficient; future story if needed |
| No `UserPolicy` in KAN-10 | Profile update is inherently scoped to authenticated user; no cross-user auth |
| Admin middleware uses `UserRole::Admin` enum | Type-safe comparison, not raw string |
| Test-only routes for middleware testing | Avoids placeholder admin endpoints with no business purpose |
| `email:rfc` validation (not `dns`) | Consistent with existing API requests; avoids DNS failures in tests |

## Expected Files

### Create

| File | Purpose |
|------|---------|
| `app/Actions/Profile/UpdateUserProfileAction.php` | Shared profile-update logic |
| `app/Http/Controllers/Api/V1/ProfileController.php` | API profile update |
| `app/Http/Requests/Api/V1/Profile/UpdateProfileRequest.php` | API validation |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Administrator middleware |
| `tests/Feature/Api/V1/ProfileApiTest.php` | API profile tests |
| `tests/Feature/Middleware/AdminMiddlewareTest.php` | Admin middleware tests |

### Modify

| File | Change |
|------|--------|
| `routes/api.php` | Add `PATCH /profile` route |
| `bootstrap/app.php` | Register `admin` middleware alias |
| `app/Http/Controllers/ProfileController.php` | Use `UpdateUserProfileAction` |
| `tests/Feature/ProfileTest.php` | Add role-security test |

## Verification Commands

```bash
# Run all tests
php artisan test

# Check code style
./vendor/bin/pint --test

# Build frontend
npm run build

# List routes
php artisan route:list --path=api
```

## Jira Acceptance Criteria Mapping

| Criterion | Requirement | Status |
|-----------|-------------|--------|
| 1. Authenticated user can consult profile | R1.1, R1.2 | Already implemented (GET /api/v1/auth/user) |
| 2. User can update name and email with validation | R2.1–R2.14 | To implement |
| 3. Logout revokes current Sanctum token | R3.1–R3.5 | Already implemented, tests to verify |
| 4. Affiliate and Admin roles represented by Enum | R4.1–R4.4 | Already implemented, tests to verify |
| 5. Middleware protects administrator routes | R5.1–R5.5 | To implement |
