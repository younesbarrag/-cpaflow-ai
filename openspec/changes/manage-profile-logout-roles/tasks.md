# Tasks: Manage Profile, Logout and Roles (KAN-10)

## Section 1: Repository Verification

- [x] **1.1** Verify Git branch is `feature/KAN-10-profile-roles`
- [x] **1.2** Verify Git working tree is clean
- [x] **1.3** Verify Laravel version: `Laravel Framework 13.20.0`
- [x] **1.4** Verify existing tests pass (`php artisan test`)
- [x] **1.5** Verify KAN-9 auth routes exist: health, register, login, logout, user
- [x] **1.6** Verify User model has `HasApiTokens` trait and `UserRole` cast
- [x] **1.7** Verify `UserRole` enum has `Affiliate` and `Admin` cases
- [x] **1.8** Verify `role` column exists on users table (not in fillable)
- [x] **1.9** Verify existing web profile routes: GET /profile, PATCH /profile, DELETE /profile

## Section 2: Shared Profile-Update Action

- [x] **2.1** Create `app/Actions/Profile/` directory
- [x] **2.2** Create `UpdateUserProfileAction.php` — receives User + array data, normalizes email, detects email change, fills name/email, resets `email_verified_at` if email changed, saves, returns User
- [x] **2.3** Verify Action does NOT accept `role`, `password`, `remember_token`, or `tokens`

## Section 3: API Profile Update — Form Request

- [x] **3.1** Create `app/Http/Requests/Api/V1/Profile/` directory
- [x] **3.2** Create `UpdateProfileRequest.php` — rules: name (required, string, max:255), email (required, email:rfc, max:255, unique:users,email ignoring self); add `prepareForValidation()` to normalize email (trim + lowercase)
- [x] **3.3** Verify `authorize()` returns true (auth:sanctum handles authentication)

## Section 4: API Profile Update — Controller and Route

- [x] **4.1** Create `app/Http/Controllers/Api/V1/ProfileController.php` with `update()` method
- [x] **4.2** `update()` delegates to `UpdateUserProfileAction`, returns HTTP 200 with `UserResource`
- [x] **4.3** Add `PATCH /api/v1/profile` route to `routes/api.php` inside `auth:sanctum` group
- [x] **4.4** Route name: `api.v1.profile.update`

## Section 5: Web ProfileController Adaptation

- [x] **5.1** Modify `app/Http/Controllers/ProfileController.php` — inject `UpdateUserProfileAction` in `update()` method
- [x] **5.2** Replace inline `fill()` + `isDirty('email')` logic with `$action->execute($request->user(), $request->validated())`
- [x] **5.3** Update `app/Http/Requests/ProfileUpdateRequest.php` — add `prepareForValidation()` to normalize email (trim + lowercase)
- [x] **5.4** Verify web profile update still works (existing ProfileTest must pass)

## Section 6: Administrator Middleware

- [x] **6.1** Create `app/Http/Middleware/EnsureUserIsAdmin.php` — checks `$request->user()->role !== UserRole::Admin`, returns 403
- [x] **6.2** Register `admin` alias in `bootstrap/app.php` `withMiddleware()`
- [x] **6.3** Verify middleware does NOT check authentication (assumes upstream auth middleware)

## Section 7: API Profile Tests

- [x] **7.1** Create `tests/Feature/Api/V1/ProfileApiTest.php`
- [x] **7.2** Test: unauthenticated PATCH returns 401
- [x] **7.3** Test: authenticated user updates name → 200, UserResource returned
- [x] **7.4** Test: authenticated user updates email → 200, email normalized
- [x] **7.5** Test: current unchanged email is accepted → 200
- [x] **7.6** Test: duplicate email returns 422
- [x] **7.7** Test: invalid email returns 422
- [x] **7.8** Test: missing name returns 422
- [x] **7.9** Test: `role=admin` input does NOT change Affiliate role
- [x] **7.10** Test: password remains unchanged after profile update
- [x] **7.11** Test: current Sanctum token remains valid after profile update
- [x] **7.12** Test: response does not contain password or remember_token

## Section 8: Admin Middleware Tests

- [x] **8.1** Create `tests/Feature/Middleware/AdminMiddlewareTest.php`
- [x] **8.2** Register test-only route with `auth:sanctum,admin` middleware
- [x] **8.3** Test: unauthenticated request returns 401
- [x] **8.4** Test: authenticated Affiliate returns 403
- [x] **8.5** Test: authenticated Admin passes (200)
- [x] **8.6** Verify middleware uses `UserRole::Admin` enum comparison

## Section 9: Web Profile Tests

- [x] **9.1** Verify existing `ProfileTest` still passes (profile page, update, delete)
- [x] **9.2** Add test: submitting `role=admin` does not change role via web profile update
- [x] **9.3** Verify email verification behavior: email change resets `email_verified_at`, unchanged email preserves it

## Section 10: Logout Verification

- [x] **10.1** Verify existing `AuthApiTest` logout tests still pass
- [x] **10.2** Verify test: current token revoked (count decreases by 1)
- [x] **10.3** Verify test: another token remains valid
- [x] **10.4** Verify test: unauthenticated logout returns 401

## Section 11: Regression

- [x] **11.1** Run full test suite — all tests pass
- [x] **11.2** Verify health endpoint still passes
- [x] **11.3** Verify KAN-9 registration tests still pass
- [x] **11.4** Verify KAN-9 login tests still pass
- [x] **11.5** Verify Breeze authentication tests still pass

## Section 12: OpenSpec Updates

- [x] **12.1** Update `openspec/changes/manage-profile-logout-roles/design.md` if any implementation differs from plan
- [x] **12.2** Update `openspec/changes/manage-profile-logout-roles/tasks.md` — mark completed tasks
- [x] **12.3** Update `docs/conception-technique.md` if significant architecture changes

## Section 13: Final Verification

- [x] **13.1** Run `./vendor/bin/pint --test` — code style compliant
- [x] **13.2** Run `npm run build` — frontend builds successfully
- [x] **13.3** Verify no sensitive data in git diff
- [x] **13.4** Verify route list: PATCH /api/v1/profile exists with auth:sanctum
- [x] **13.5** Verify no database migration was created or executed
