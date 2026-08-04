# Proposal - KAN-22: Administration des utilisateurs

## 1. Summary

Add an admin-only user management API that allows authenticated administrators to list all users (with pagination, search, and role filtering), view individual user details, and update a user's role. The feature is API-only — no Blade UI. Authorization is enforced via three layers: `EnsureUserIsAdmin` middleware (namespace gate), `UserPolicy` (per-ability including self-demotion), and `UpdateUserRoleAction` (transaction-safe last-admin invariant with admin-set locking). The admin has no access to other users' business resources (Offers, Campaigns) — admin scope is strictly account management.

## 2. Problem

The application has two user roles (`affiliate`, `admin`) defined in the `UserRole` enum, and an `EnsureUserIsAdmin` middleware already exists and is tested. However, there are no admin API endpoints to manage users. An administrator cannot list users, view user details, or change a user's role through the API. Without these capabilities, user administration requires direct database access, which is unsustainable for production use.

## 3. Objectives

- Let an Admin list all users via `GET /api/v1/admin/users` with pagination, search (name/email), and role filter.
- Let an Admin view a single user via `GET /api/v1/admin/users/{user}`.
- Let an Admin update a user's role via `PATCH /api/v1/admin/users/{user}`.
- Enforce admin-only access via the existing `admin` middleware alias.
- Prevent self-demption (Admin cannot change their own role) — authorization layer.
- Prevent last-admin demotion (the application must never end with zero Admin users) — domain invariant in Action, transaction-safe with admin-set locking.
- Same-role requests (admin→admin, affiliate→affiliate) return 200 with no effective mutation.
- Never expose sensitive fields (password, remember_token, token hashes).
- Preserve the existing 570/570 test baseline.

## 4. In Scope

- `UserPolicy` with `viewAny`, `view`, `updateRole` abilities.
- `AdminUserController` with `index`, `show`, `update` methods.
- `AdminUserResource` — admin-specific JSON serialization (extended fields).
- `UpdateUserRoleRequest` — Form Request for role update validation.
- `ListUsersAction` — paginated user listing with search/filter.
- `GetUserAction` — single user retrieval.
- `UpdateUserRoleAction` — role update with transaction-safe last-admin invariant (locks all Admin rows).
- `UserFactory::admin()` state — for test and seeding purposes.
- Pest tests covering security, list, show, role update, self-protection, same-role, concurrency invariant, and regression.
- Postman/Newman collection (limited scope — see Design §17).
- Documentation update.

## 5. Out of Scope

- User deletion (would cascade through Offers, Campaigns, TrackingLinks, TrackingClicks, Conversions, CampaignExpenses, AiAnalyses, AiGenerations — destructive and not required).
- Password reset by Admin.
- Impersonation / login-as.
- Session revocation UI.
- Banning / account deactivation (no `is_active`/`status` column exists).
- Email verification override.
- Bulk user actions.
- CSV export.
- Admin analytics dashboard.
- Audit trail / activity log.
- User soft deletes.
- GDPR deletion workflow.
- OAuth administration.
- Role/permission package (Spatie Permission etc.) — `UserRole` enum is sufficient.
- Blade frontend for admin (API-only in KAN-22).
- Admin bypass to Offer/Campaign ownership policies.
- Deterministic admin seeding (no seeder added in KAN-22).

## 6. Success Criteria

- Admin can list users → 200 with paginated list.
- Admin can search users by name/email → filtered results.
- Admin can filter users by role → filtered results.
- Admin can view a user → 200 with user details.
- Admin can update a user's role → 200 with updated user.
- Same-role update (admin→admin or affiliate→affiliate) → 200, no effective mutation.
- Invalid role → 422.
- Admin updating own role → 403 (self-demotion, Policy layer).
- Demoting the last Admin → 409 Conflict (Action layer, transaction-safe with admin-set locking).
- Non-admin authenticated user → 403 on all admin endpoints.
- Guest → 401 on all admin endpoints.
- Unknown user → 404.
- Sensitive fields never exposed.
- Admin has no access to foreign Offers/Campaigns.
- All 570+ tests pass. Pint clean.
