# Tasks: Implement Secure Authentication (KAN-9)

## Section 1: Repository and Dependency Verification

- [x] **1.1** Verify Git branch is `feature/KAN-9-secure-authentication`
- [x] **1.2** Verify Git working tree is clean (at time of implementation start)
- [x] **1.3** Verify Laravel version: `Laravel Framework 13.20.0`
- [x] **1.4** Verify PHP version: PHP 8.4.20
- [x] **1.5** Verify existing tests pass
- [x] **1.6** Verify existing health endpoint works

## Section 2: Breeze Blade Installation

- [x] **2.1** Install Laravel Breeze via Composer (`laravel/breeze v2.4.2`)
- [x] **2.2** Run `breeze:install blade` — Blade views, routes, controllers, and assets scaffolded
- [x] **2.3** Verify `routes/web.php` was not broken — welcome route preserved, dashboard route added
- [x] **2.4** Verify `routes/api.php` was not modified by Breeze — health route preserved
- [x] **2.5** Install npm dependencies
- [x] **2.6** Build frontend assets — `npm run build` succeeds
- [x] **2.7** Verify Blade pages work — `/register` and `/login` render correctly

## Section 3: Sanctum Installation

- [x] **3.1** Install Laravel Sanctum via Composer (`laravel/sanctum v4.3.2`)
- [x] **3.2** Publish Sanctum migration — `personal_access_tokens` migration published
- [x] **3.3** Verify `routes/api.php` was NOT overwritten by `install:api` — manual install used

## Section 4: User Model — HasApiTokens and Role Migration

- [x] **4.1** Create migration to add `role` column: `string('role', 20)`, default `affiliate`, indexed, no `after('email')`
- [x] **4.2** Run pending migrations — Sanctum migration and role column migration executed
- [x] **4.3** Add `HasApiTokens` trait to User model, add `UserRole` cast, verify `role` NOT in `#[Fillable]`
- [x] **4.4** Verify migration status — all migrations marked as "Ran"

## Section 5: Shared Registration Action

- [x] **5.1** Create `RegisterUserAction` — explicit `fill()` + property assignment, no `forceFill`/`forceCreate`
- [x] **5.2** Registration action tested via integration tests (role escalation, password hashing, etc.)

## Section 6: API Form Requests and Resources

- [x] **6.1** Create `RegisterApiRequest` — `name`, `email` (unique), `password` (min:8, confirmed); no `device_name`
- [x] **6.2** Create `LoginApiRequest` — `email`, `password`, optional `device_name`
- [x] **6.3** Create `UserResource` — returns `id`, `name`, `email`, `role`; excludes sensitive fields

## Section 7: API Authentication Controllers and Routes

- [x] **7.1-7.4** Create combined `AuthController` with `login()`, `register()`, `logout()`, `user()` methods (intentional deviation from planned 4 separate controllers — stays thin and cohesive at ~85 lines)
- [x] **7.5** Add API auth routes to `routes/api.php`:
  - `POST /register` with `throttle:api-register`
  - `POST /login` with **no** throttle middleware (Action handles lifecycle)
  - `POST /logout` with `auth:sanctum`
  - `GET /user` with `auth:sanctum`

## Section 8: Web Authentication Customization

- [x] **8.1** Customize Breeze `RegisteredUserController` to use `RegisterUserAction` — web registration now assigns `UserRole::Affiliate`
- [x] **8.2** Verify Breeze `LoginRequest` provides built-in rate limiting — confirmed, no duplicate middleware
- [x] **8.3** Verify web registration creates user with Affiliate role — tested

## Section 9: Rate Limiting

- [x] **9.1** Register `api-register` rate limiter in `AppServiceProvider` — 10/min by IP. Unused `api-login` limiter removed.
- [x] **9.2** Add `throttle:api-register` middleware to API register route only. Login route has NO throttle middleware.

## Section 10: Database Migration Verification

- [x] **10.1** Verify all migrations run successfully
- [x] **10.2** Verify `users` table has `role` column (string(20), default 'affiliate', indexed)
- [x] **10.3** Verify `personal_access_tokens` table exists

## Section 11: Web Authentication Tests

- [x] **11.1** Web registration tests: page accessible, valid registration, Affiliate role, hashed password, duplicate email rejected, validation errors
- [x] **11.2** Web login tests: page accessible, valid login, invalid credentials, logout (Breeze defaults)

## Section 12: API Authentication Tests

- [x] **12.1** API registration tests: 201, Affiliate role, no token returned, hashed password, role escalation blocked, duplicate email 422, validation 422, password confirmation required, no password/remember_token in response, throttling 429
- [x] **12.2** API login tests: 200, token returned, token_type Bearer, optional device_name, default api-client, token authenticates protected route, wrong password 401, unknown email 401, validation 422, 5 failures trigger 429, validation errors don't increment limiter, successful login clears failures
- [x] **12.3** API logout tests: authenticated logout 200, revokes only current token (verified via DB count), unauthenticated 401
- [x] **12.4** API user tests: authenticated returns UserResource, no password/remember_token, unauthenticated 401

## Section 13: Security and Regression Verification

- [x] **13.1** Verify existing health endpoint test still passes
- [x] **13.2** Run full test suite — 57/57 pass
- [x] **13.3** Verify Pint compliance — passed
- [x] **13.4** Fix Pint violations — auto-fixed, re-verified

## Section 14: Final Validation

- [x] **14.1** Verify all API routes registered correctly — 5 routes: health, login, register, logout, user
- [x] **14.2** Verify web routes registered correctly — Breeze auth routes present
- [x] **14.3** Manual API test verified via automated tests
- [x] **14.4** Verify no sensitive data in git diff — no passwords, tokens, or secrets

## Jira Acceptance Criteria Mapping

| Criterion | Status | Task(s) |
|-----------|--------|---------|
| 1. Valid registration creates Affiliate user | ✅ | 4.1, 4.3, 5.1, 8.1, 11.1, 12.1 |
| 2. Email must be unique | ✅ | 6.1, 11.1, 12.1 |
| 3. Invalid API registration returns 422 | ✅ | 6.1, 12.1 |
| 4. Valid API login returns Sanctum token | ✅ | 4.3, 7.1-7.5, 12.2 |
| 5. Incorrect API credentials return 401 | ✅ | 7.1-7.5, 12.2 |
| 6. Blade pages work | ✅ | 2.2, 2.7, 11.1, 11.2 |
| 7. Passwords are securely hashed | ✅ | 5.1, 11.1, 12.1 |
| 8. Login is rate limited | ✅ | 7.1-7.5, 9.1, 9.2, 12.2 |

## Implementation Notes

- **Combined AuthController** (intentional deviation): Used a single `AuthController` (~85 lines) instead of 4 separate invokable controllers. Stays thin and cohesive. Documented in design.md.
- **RegisterUserAction** is shared by both web (`RegisteredUserController`) and API (`AuthController`).
- **No RegisterResult DTO**: Removed because registration always succeeds (rate limiting is middleware-owned). Action returns `User` directly.
- **LoginResult DTO**: Retained because login has 3 outcomes (success, failed, throttled).
- **Logout uses** `$request->user()->currentAccessToken()->delete()` for current-token-only revocation.
- **Migration rollback** explicitly drops index before column for safety.
- **Registration does NOT return a token**: Client must call `/api/v1/auth/login` separately.
