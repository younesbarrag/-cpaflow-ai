# Design - KAN-22: Administration des utilisateurs

## 1. Existing Infrastructure

KAN-22 reuses existing infrastructure. No new middleware, enum, or migration is needed.

| Component | Status | Location |
|-----------|--------|----------|
| `UserRole` enum (`affiliate`, `admin`) | Exists | `app/Enums/UserRole.php` |
| `role` column on `users` table | Exists | Migration `2026_07_20_093520` |
| `role` cast on User model | Exists | `app/Models/User.php` |
| `EnsureUserIsAdmin` middleware | Exists | `app/Http/Middleware/EnsureUserIsAdmin.php` |
| `admin` middleware alias | Exists | `bootstrap/app.php` |
| Admin middleware tests | Exists (4/4 PASS) | `tests/Feature/Middleware/AdminMiddlewareTest.php` |
| `UserResource` (public) | Exists | `app/Http/Resources/Api/V1/UserResource.php` |

## 2. No Database Migration

The `users` table already has all columns KAN-22 requires:
- `id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `role`, `created_at`, `updated_at`

No new columns are needed. No migration will be created.

## 3. Three-Layer Authorization Architecture

KAN-22 uses three distinct authorization layers, each with a clear responsibility:

### Layer 1: Middleware — Namespace Gate

`EnsureUserIsAdmin` protects the entire `/admin` route group. Non-admin users receive 403 before any controller logic executes. This is a coarse-grained gate — it does not know which specific ability is being invoked.

### Layer 2: Policy — Per-Ability Authorization

`UserPolicy` handles fine-grained authorization per ability. For `updateRole`, it enforces the **self-demotion rule**: the authenticated Admin cannot change their own role. This is an authorization decision — "is this caller allowed to perform this action on this target?" — and belongs in the Policy.

### Layer 3: Action — Domain Invariant

`UpdateUserRoleAction` enforces the **last-admin invariant**: the application must never end with zero Admin users. This is a business integrity rule, not an authorization decision. It belongs in the Action because:
- It requires a DB transaction with locking of the admin set (infrastructure concern of the Action).
- It depends on the current state of other rows (admin count), not just the caller's identity.
- It may throw a `ConflictHttpException` (409), which is a domain error, not an auth error.

| Layer | Responsibility | Error |
|-------|---------------|-------|
| `admin` middleware | Block non-admin from `/admin` namespace | 403 |
| `UserPolicy::updateRole` | Admin only AND target must not be self | 403 |
| `UpdateUserRoleAction` | Transaction-safe last-admin invariant | 409 |

### Why separation matters

- Self-demption is **authorization** — "can this caller do this?" → Policy.
- Last-admin is **domain invariant** — "would this mutation leave the system in an invalid state?" → Action.
- Mixing them would force the Policy to execute raw DB queries with locking, violating the single-responsibility principle and the project's existing Policy patterns.

## 4. Admin Middleware (unchanged)

The `EnsureUserIsAdmin` middleware already:
- Returns JSON `403` with `{"message": "Forbidden."}` for non-admin users
- Uses `UserRole::Admin` enum comparison
- Is registered as `'admin'` alias in `bootstrap/app.php`
- Is tested with 4 Pest tests

KAN-22 applies this middleware to the admin route group. No middleware changes required.

## 5. UserPolicy Design

### Abilities

| Ability | Rule | Error |
|---------|------|-------|
| `viewAny(User $actor)` | `$actor->role === UserRole::Admin` | 403 |
| `view(User $actor, User $target)` | `$actor->role === UserRole::Admin` | 403 |
| `updateRole(User $actor, User $target)` | `$actor->role === UserRole::Admin` AND `$actor->id !== $target->id` | 403 |

The Policy receives the authenticated actor as the first parameter. No `Auth::id()` call is needed — the actor is already available.

The `updateRole` ability does NOT check the last-admin invariant. That is the Action's responsibility.

### No Admin Bypass in Existing Policies

`OfferPolicy` and `CampaignPolicy` check `ownsOffer`/`ownsCampaign` only. KAN-22 does NOT add admin bypass to these policies. Admin access to business resources is explicitly excluded from scope.

## 6. Self-Demotion Protection (Policy Layer)

An Admin cannot update their own role. `UserPolicy::updateRole` returns `false` when `$actor->id === $target->id` → 403.

**Rationale:** An Admin accidentally demoting themselves would lock them out of admin functionality. This is a preventable user error.

**Where it lives:** Policy layer (authorization), not Action layer.

## 7. Last-Admin Invariant (Action Layer)

If a role update would leave the application with zero Admin users, the Action rejects the mutation with 409 Conflict.

**When it triggers:** The target user is currently an Admin, AND the requested new role is not `Admin`, AND after the mutation there would be zero Admin users.

**Where it lives:** `UpdateUserRoleAction`, inside a DB transaction with admin-set locking.

**Why not in Policy:** The Policy cannot safely perform transactional reads with locking. The invariant depends on the current state of other rows and must be evaluated atomically with the mutation.

## 8. Concurrency-Safe Demotion Algorithm

The last-admin invariant must be transaction-safe. A single-row `lockForUpdate()` is NOT sufficient — locking only the target row does not prevent concurrent demotions of different Admin rows.

### Race condition

```
Initial state: Admin A, Admin B (2 admins)

Request 1: A demotes B
Request 2: B demotes A

Without proper locking:
  Transaction 1 reads admin count = 2, proceeds
  Transaction 2 reads admin count = 2, proceeds
  Both commit → zero admins
```

### Deterministic locking strategy

`UpdateUserRoleAction` wraps the mutation in `DB::transaction()` and locks the **entire set of current Admin rows**:

```php
public function execute(User $actor, User $target, string $newRole): User
{
    return DB::transaction(function () use ($actor, $target, $newRole) {
        // 1. Lock ALL current Admin rows (deterministic ORDER BY to reduce deadlock risk)
        $adminUsers = User::query()
            ->where('role', UserRole::Admin)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // 2. Reload the target user inside the transaction for current state
        $lockedTarget = User::lockForUpdate()->find($target->id);

        // 3. Same-role: return early, no mutation needed
        if ($lockedTarget->role->value === $newRole) {
            return $lockedTarget;
        }

        // 4. If demoting an Admin, check count from the locked admin set
        if ($lockedTarget->role === UserRole::Admin && $newRole !== UserRole::Admin->value) {
            if ($adminUsers->count() <= 1) {
                throw new ConflictHttpException('Cannot demote the last administrator.');
            }
        }

        // 5. Persist
        $lockedTarget->role = $newRole;
        $lockedTarget->save();

        return $lockedTarget;
    });
}
```

### Why this works

The `lockForUpdate()` on the admin-query with `orderBy('id')` locks all rows matching `role = 'admin'` (and potentially gap locks on the index range). When two concurrent transactions attempt to demote different Admin users:

1. Transaction 1 acquires locks on all Admin rows → proceeds, sees count = 2 → demotes → commits → releases locks.
2. Transaction 2 acquires locks on all Admin rows (now only 1 Admin remains after T1 committed) → sees count = 1 → throws 409.

The `orderBy('id')` ensures a deterministic lock acquisition order, reducing deadlock risk when two transactions lock the same set.

### Acknowledged behavior

MySQL/InnoDB `lockForUpdate` with a range query uses next-key locks (index + gap). The `orderBy('id')` combined with `where('role', 'admin')` locks the relevant index entries. This is sufficient for the expected admin operations in this application. If the application scales to very high concurrent admin operations, a higher-level advisory lock could be considered as a future enhancement.

## 9. Same-Role Behavior

| Requested role | Current role | Result |
|---------------|-------------|--------|
| `admin` | `admin` | 200 OK, no effective mutation, `updated_at` unchanged |
| `affiliate` | `affiliate` | 200 OK, no effective mutation, `updated_at` unchanged |

The Action detects same-role requests early (before the admin-set lock) and returns the target user without persisting. This avoids unnecessary DB updates and lock acquisition.

## 10. User Resource Shape

### 10a. Public UserResource (existing, unchanged)

Used by `AuthController::user()` and login response. Exposes:
- `id`, `name`, `email`, `role`

### 10b. AdminUserResource (new)

Used by admin endpoints. Extends the public shape with:
- `id`, `name`, `email`, `role`, `email_verified_at`, `created_at`, `updated_at`

**Never exposed:** `password`, `remember_token`, `tokens` (Sanctum), any internal auth secrets.

The `User` model's `#[Hidden(['password', 'remember_token'])]` attribute already prevents these from being serialized by `toArray()`, but the Resource explicitly whitelists fields for defense-in-depth.

## 11. Search and Filters

### 11a. Search

Partial `LIKE %term%` match against `name` and `email` columns. Whether matching is case-insensitive follows the configured database collation. No `LOWER()` or custom collation is applied unless evidence requires it.

**Parameter:** `?search=term`

### 11b. Role Filter

Filter by exact role value. Validated against `UserRole` enum.

**Parameter:** `?role=admin` or `?role=affiliate`

### 11c. Combined

Search and role filter can be combined. Both are optional.

## 12. Pagination

Laravel's default pagination (15 per page). Uses `paginate()` which returns:
- `data` — array of resources
- `links` — pagination URLs
- `meta` — total, per_page, current_page, last_page

**Response envelope:** The paginated result is returned directly under `data` (Laravel ResourceCollection convention).

## 13. Role Update Contract

### Request

```
PATCH /api/v1/admin/users/{user}
Content-Type: application/json
Authorization: Bearer <admin-token>

{
  "role": "affiliate"
}
```

### Validation

- `role` : required, string, must exist in `UserRole` enum values (`affiliate`, `admin`).
- Only the `role` field is consumed. Body fields `id`, `name`, `email`, `password`, `remember_token` are NOT accepted by this endpoint.

### Response 200

```json
{
  "data": {
    "id": 2,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "affiliate",
    "email_verified_at": "2026-07-20T12:00:00Z",
    "created_at": "2026-07-20T12:00:00Z",
    "updated_at": "2026-08-04T12:00:00Z"
  }
}
```

### Response 403 (self-demotion or non-admin)

```json
{
  "message": "Forbidden."
}
```

### Response 409 (last admin demotion)

```json
{
  "message": "Cannot demote the last administrator."
}
```

## 14. HTTP Status Matrix

| Scenario | HTTP | Layer |
|----------|------|-------|
| Guest on any admin endpoint | 401 | Middleware |
| Non-admin on any admin endpoint | 403 | Middleware |
| Admin lists users | 200 | — |
| Admin shows existing user | 200 | — |
| Admin shows unknown user | 404 | — |
| Admin updates user role (valid, different) | 200 | — |
| Admin same-role update (admin→admin) | 200 | Action (no-op) |
| Admin same-role update (affiliate→affiliate) | 200 | Action (no-op) |
| Admin updates role with invalid value | 422 | FormRequest |
| Admin updates own role (self-demotion) | 403 | Policy |
| Admin demotes last admin | 409 | Action |
| Admin body attempts to modify id/email/name | 422 | FormRequest |

## 15. Action Classes

Following the project's Controller → FormRequest/Policy → Action → Model → Resource pattern:

| Action | Purpose |
|--------|---------|
| `ListUsersAction` | Paginated user listing with search/filter |
| `GetUserAction` | Single user retrieval (404 if not found) |
| `UpdateUserRoleAction` | Role update with transaction-safe last-admin invariant |

Each Action is a single-use class with an `execute()` method. Business logic lives in Actions, not Controllers.

### UpdateUserRoleAction execution flow

```
execute(User $actor, User $target, string $newRole): User
  1. Same-role check: if target->role === newRole → return target (no mutation)
  2. DB::transaction:
     a. Lock ALL current Admin rows: User::where('role', Admin)->orderBy('id')->lockForUpdate()->get()
     b. Reload target: User::lockForUpdate()->find($target->id)
     c. Re-check same-role (state may have changed under lock)
     d. If demoting an Admin:
        - Count from locked admin set
        - If count <= 1 → throw ConflictHttpException (409)
     e. Set role = $newRole
     f. Save
  3. Return updated User
```

Self-demption is NOT checked here — it is handled by `UserPolicy::updateRole` before the Action is called.

## 16. Factory State

Add `admin()` state to `UserFactory`:

```php
public function admin(): static
{
    return $this->state(fn (array $attributes) => [
        'role' => UserRole::Admin,
    ]);
}
```

Used by tests.

## 17. Seeders

KAN-22 does NOT modify `DatabaseSeeder`. No admin seeder is created. Admin users are created via the factory in tests. Production admin creation would be done via tinker or a future dedicated command.

**No deterministic admin account exists in the current codebase.** The `DatabaseSeeder` creates only one affiliate user (`test@example.com`). The `UserFactory` has no `admin()` state yet.

## 18. Security

- **Admin routes** protected by `auth:sanctum` + `admin` middleware.
- **UserPolicy** provides per-ability authorization including self-demotion check (uses `$actor` parameter, no `Auth::id()`).
- **UpdateUserRoleAction** enforces last-admin invariant in a transaction with admin-set locking.
- **Mass assignment:** `role` is NOT in `User::$fillable` (Laravel 13 `#[Fillable]` attribute only lists `name`, `email`, `password`). The `UpdateUserRoleAction` sets `$user->role = $newRole` explicitly — no mass assignment risk.
- **Sensitive fields:** `password`, `remember_token` excluded by `#[Hidden]` attribute and by explicit field whitelisting in `AdminUserResource`.
- **No business ownership bypass:** Admin cannot access foreign Offers/Campaigns via KAN-22.
- **No privilege escalation:** Registration endpoint does not accept `role` field. Profile update does not accept `role` field.
- **Form Request scope:** `UpdateUserRoleRequest` only accepts `role`. Fields `id`, `name`, `email`, `password`, `remember_token` are not consumed.

## 19. Postman/Newman

**No deterministic admin account exists.** Newman cannot reliably authenticate as an Admin without manufacturing privileged access (which is explicitly prohibited). Pest is authoritative for all admin-success scenarios.

**Newman verifies only deterministic HTTP flows:**
1. Health check → 200
2. Register normal user → 201
3. Login normal user → 200 (token acquired)
4. Normal user calls `GET /api/v1/admin/users` → 403
5. Normal user calls `GET /api/v1/admin/users/{id}` → 403
6. Normal user calls `PATCH /api/v1/admin/users/{id}` → 403
7. Guest calls `GET /api/v1/admin/users` → 401

**Pest is authoritative for:**
- Admin list success (200)
- Admin show success (200)
- Admin role update success (200)
- Same-role update (200)
- Self-demotion (403)
- Last-admin invariant (409)
- Invalid role (422)
- Unknown user (404)
- Ownership regression (Admin on foreign Offer → 403)

KAN-23 may later provide deterministic demo/seed data if required.

## 20. CI Compatibility

KAN-25 CI is verified and unchanged. KAN-22 tests:
- Use SQLite in-memory (same as local dev)
- No external services
- No npm dependencies
- Pass unchanged `Backend Tests` and `Frontend Build` jobs

The full Pest suite runs on MySQL 8.4 in CI (KAN-25 workflow). SQLite is used for local development and test execution speed. The admin-set locking strategy is designed for MySQL/InnoDB behavior.

## 21. Documentation

Update `docs/conception-technique.md`:
- Add admin user management routes to the API routes section
- Document `UserPolicy` abilities (using `$actor` parameter, no `Auth::id()`)
- Document self-demotion (Policy layer) and last-admin invariant (Action layer, transaction-safe with admin-set locking) rules
