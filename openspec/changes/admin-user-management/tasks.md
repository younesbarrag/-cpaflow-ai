# Tasks - KAN-22: Administration des utilisateurs

## 1. Factory

- [x] **T1.1** Add `admin()` state to `database/factories/UserFactory.php` — returns `['role' => UserRole::Admin]`. Follow existing `unverified()` state pattern.

## 2. Policy

- [x] **T2.1** Create `app/Policies/UserPolicy.php` — abilities: `viewAny(User $actor)`, `view(User $actor, User $target)`, `updateRole(User $actor, User $target)`. `viewAny` and `view` require `$actor->role === UserRole::Admin`. `updateRole` requires `$actor->role === UserRole::Admin` AND `$actor->id !== $target->id`. Use the `$actor` parameter (first argument) — do NOT call `Auth::id()`. The last-admin invariant is NOT checked here — it belongs in the Action.

## 3. Resource

- [x] **T3.1** Create `app/Http/Resources/Api/V1/AdminUserResource.php` — fields: `id`, `name`, `email`, `role`, `email_verified_at`, `created_at`, `updated_at`. Explicitly whitelisted (defense-in-depth over `#[Hidden]`).

## 4. Form Request

- [x] **T4.1** Create `app/Http/Requests/Api/V1/Admin/UpdateUserRoleRequest.php` — `authorize()` returns `Gate::authorize('updateRole', $this->route('user'))`. Rules: `role` required, string, `in:affiliate,admin` (validated against `UserRole::cases()`). Only `role` is consumed — body fields `id`, `name`, `email`, `password`, `remember_token` are NOT accepted.

## 5. Actions

- [x] **T5.1** Create `app/Actions/Admin/ListUsersAction.php` — accepts optional `?search` and `?role` params. Applies `LIKE %term%` on `name`/`email` for search. Applies exact `where('role', $role)` for filter. Returns `User::paginate(15)`.
- [x] **T5.2** Create `app/Actions/Admin/GetUserAction.php` — retrieves User by route key (`{user}`). Throws `ModelNotFoundException` if not found (auto 404).
- [x] **T5.3** Create `app/Actions/Admin/UpdateUserRoleAction.php` — accepts `$actor` (authenticated admin), `$target` (user to update), `$newRole`. Same-role check first: if `$target->role->value === $newRole` → return target without mutation. Otherwise, execute inside `DB::transaction()`: lock ALL current Admin rows with `User::where('role', UserRole::Admin)->orderBy('id')->lockForUpdate()->get()`. Reload target with `User::lockForUpdate()->find($target->id)`. Re-check same-role under lock. If demoting an Admin (`$lockedTarget->role === UserRole::Admin && $newRole !== UserRole::Admin->value`): count from locked admin set; if count <= 1, throw `ConflictHttpException` (409). Set `$lockedTarget->role = $newRole` and save within the transaction. Self-demption is NOT checked here — it is handled by `UserPolicy::updateRole` before the Action is called.

## 6. Controller

- [x] **T6.1** Create `app/Http/Controllers/Api/V1/AdminUserController.php` — `index()` calls `ListUsersAction`, returns `AdminUserResource::collection($paginator)`. `show($user)` calls `GetUserAction`, returns `new AdminUserResource($user)`. `update(UpdateUserRoleRequest $request, $user)` calls `UpdateUserRoleAction` with `auth()->user()` as actor, returns `new AdminUserResource($user)`.
- [x] **T6.2** All methods use `Gate::authorize()` or FormRequest `authorize()` for authorization. Controller is thin — no business logic.

## 7. Routes

- [x] **T7.1** Register admin routes in `routes/api.php` inside `auth:sanctum` + `admin` middleware group:
  - `GET /api/v1/admin/users` → `AdminUserController@index` → `api.v1.admin.users.index`
  - `GET /api/v1/admin/users/{user}` → `AdminUserController@show` → `api.v1.admin.users.show`
  - `PATCH /api/v1/admin/users/{user}` → `AdminUserController@update` → `api.v1.admin.users.update`
- [x] **T7.2** Admin route group prefix: `admin`. All routes under `auth:sanctum` + `admin` middleware.

## 8. Tests

- [x] **T8.1** Create `tests/Feature/Api/V1/AdminUserApiTest.php` — security tests:
  - Guest on index → 401
  - Guest on show → 401
  - Guest on update → 401
  - Affiliate on index → 403
  - Affiliate on show → 403
  - Affiliate on update → 403
- [x] **T8.2** List tests:
  - Admin lists users → 200, response contains `data` array
  - Admin lists users → pagination `meta` present
  - Sensitive fields (password, remember_token) absent from response
- [x] **T8.3** Search/filter tests:
  - `?search=name` → filtered results
  - `?role=admin` → filtered results
  - `?search=term&role=affiliate` → combined filter
  - Empty results → 200 with empty `data`
- [x] **T8.4** Show tests:
  - Admin shows existing user → 200
  - Admin shows unknown user → 404
  - Admin shows self → 200
- [x] **T8.5** Role update tests:
  - Valid role update (affiliate→admin) → 200
  - Valid role update (admin→affiliate when another Admin remains) → 200
  - Same-role update (admin→admin) → 200, no mutation
  - Same-role update (affiliate→affiliate) → 200, no mutation
  - Invalid role → 422
  - Self-demotion → 403
  - Last-admin demotion → 409
  - Non-admin cannot update → 403
- [x] **T8.6** Body scope tests:
  - PATCH with `name` field → name is NOT modified (only `role` consumed)
  - PATCH with `email` field → email is NOT modified
- [x] **T8.7** Ownership regression tests:
  - Admin on foreign Offer → 403 (OfferPolicy unchanged)
  - Admin on foreign Campaign → 403 (CampaignPolicy unchanged)
- [x] **T8.8** Invariant tests:
  - Two Admins exist; Admin A demotes Admin B → 200 (safe, 1 admin remains)
  - Single Admin; another Admin attempts to demote that Admin → 409
- [x] **T8.9** Full suite regression: all existing tests pass (570+ baseline).

## 9. Postman/Newman

- [x] **T9.1** Create `postman/CPAFlow-AI-KAN-22.postman_collection.json` — limited scope (no deterministic admin account): Health → 200, Register → 201, Login → 200, affiliate calls admin index → 403, affiliate calls admin show → 403, affiliate calls admin update → 403, guest calls admin index → 401.

## 10. Documentation

- [x] **T10.1** Update `openspec/changes/admin-user-management/README.md` — status, files, quick start, key decisions.
- [x] **T10.2** Update `docs/conception-technique.md` — add admin routes, UserPolicy (`$actor` parameter, no `Auth::id()`), self-demption (Policy layer), last-admin invariant (Action layer, transaction-safe with admin-set locking).

## 11. Regression

- [x] **T11.1** Run KAN-9/KAN-10 auth/role tests.
- [x] **T11.2** Run Offer tests.
- [x] **T11.3** Run Campaign tests.
- [x] **T11.4** Run full suite — 600 PASS.
- [x] **T11.5** Run `vendor/bin/pint --test`.

---

**Total implementation checkboxes: 28. Completed: 28. Remaining: 0.**
