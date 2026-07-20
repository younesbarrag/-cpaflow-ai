# Proposal: Implement Secure Authentication (KAN-9)

## Problem

The CPAFlow AI Laravel backend currently has no authentication system. Users cannot register accounts or log in. The application cannot secure API endpoints or associate actions with specific users. Without authentication, the system cannot function as a multi-user platform.

## Objective

Implement secure user registration and login using Laravel Breeze for web interfaces and Laravel Sanctum for API Bearer Token authentication. Ensure password security, rate limiting, role-based access, and a comprehensive test suite.

## Current State

- **Git Branch:** `feature/KAN-9-secure-authentication` (clean working tree)
- **Laravel:** 13.20.0
- **PHP:** 8.4.20
- **Node.js:** v24.11.1, npm: 11.6.2
- **Database:** MySQL `cpaflow_ai` with `users`, `cache`, `jobs` tables migrated
- **User Model:** `App\Models\User` with `#[Fillable]` attribute, `password` cast as `hashed`
- **UserRole Enum:** `App\Enums\UserRole` with `affiliate` and `admin` cases
- **Routes:**
  - `routes/web.php` — default welcome route only
  - `routes/api.php` — `GET /api/v1/health` endpoint
- **Tests:** Pest 4.7.5 with 3 existing tests (HealthTest, ExampleTest, Unit Example)
- **PHPUnit.xml:** SQLite in-memory for test database
- **Frontend:** Vite + Tailwind CSS 4, minimal resources
- **No Breeze installed**
- **No Sanctum installed**
- **No authentication of any kind**

## In-Scope Work

1. Install Laravel Breeze and scaffold Blade authentication
2. Install Laravel Sanctum for API token authentication
3. Add `HasApiTokens` trait to User model (mandatory for `createToken()`)
4. Preserve existing `routes/api.php` and `/api/v1` versioning
5. Add `role` column to `users` table (default: `affiliate`, portable `string(20)`)
6. Create shared `RegisterUserAction` with explicit trusted role assignment
7. Create API Form Requests (`RegisterApiRequest`, `LoginApiRequest` with optional `device_name`)
8. Create API User Resource for structured responses
9. Create API authentication controllers with explicit rate-limiting lifecycle in `LoginController`
10. Add rate limiting to API login (controller-level, not route middleware)
11. Add rate limiting to API registration (route middleware)
12. Do NOT add duplicate rate limiting to web login (Breeze handles it internally)
13. Customize Breeze web registration to assign `affiliate` role via `RegisterUserAction`
14. Write comprehensive Pest tests for web and API authentication
15. Verify existing KAN-8 tests still pass
16. Run Pint formatting and asset build

## Out-of-Scope Work

- Social login (OAuth, socialite)
- Two-factor authentication
- Passkeys
- Email verification customization (standard Breeze scaffolding only)
- Password reset customization (standard Breeze scaffolding only)
- Profile management customization
- Organizations or teams
- Admin user management
- Custom permissions system
- Offers, campaigns, tracking links, clicks, conversions
- AI analysis and content generation
- Docker, GitHub Actions, Azure deployment
- Queues, jobs, queue workers

## Expected Value

- Users can create accounts with the Affiliate role
- Users can log in via web interface or API
- API clients can authenticate with Bearer Tokens created via `HasApiTokens::createToken()`
- Passwords are securely hashed and never exposed
- API login is protected by explicit rate limiting with proper increment/clear lifecycle
- Web login is protected by Breeze's built-in rate limiting (no duplication)
- The system is ready for authorization middleware in future stories

## Dependencies

- Laravel Breeze v2.4.2 (compatible with Laravel 13)
- Laravel Sanctum v4.0
- MySQL `cpaflow_ai` database (already migrated)
- Existing `UserRole` enum (already created in KAN-8)

## Risks

### R1: Breeze is a Legacy Package

**Risk:** Laravel Breeze is described as "for Laravel 11.x and prior" in its README. While it is technically compatible with Laravel 13 (confirmed by Packagist `^11.0|^12.0|^13.0` illuminate dependencies and merged PR #480), it may not receive future updates.

**Mitigation:** Breeze v2.4.2 has confirmed Laravel 13 support. The package publishes all code to the application, so we maintain full control. The Jira story explicitly requires Breeze Blade.

### R2: `install:api` Overwrites Routes

**Risk:** `php artisan install:api` will overwrite `routes/api.php` by default, destroying the KAN-8 health endpoint.

**Mitigation:** Do NOT use `install:api`. Install Sanctum manually via Composer. Configure it without touching route files.

### R3: Breeze Scaffolding Modifies Multiple Files

**Risk:** `breeze:install blade` modifies `routes/web.php`, creates `routes/auth.php`, adds views, modifies `bootstrap/app.php`, and adds npm dependencies.

**Mitigation:** Review all modified files after installation. Ensure the existing welcome route and API routes are not broken. The Breeze installation is designed to work alongside existing code.

### R4: Rate Limiting Flakiness in Tests

**Risk:** Rate limiting tests may fail depending on timing and previous test state.

**Mitigation:** Use `RateLimiter::clear()` in `beforeEach` hook. Use in-memory cache for tests. Avoid timing-dependent assertions.

### R5: HasApiTokens Omission

**Risk:** Forgetting to add `HasApiTokens` trait to User model would cause `createToken()` method not found errors at runtime.

**Mitigation:** Task 4.3 explicitly requires adding the trait. Tests in Section 12 verify token creation works.

## Acceptance Criteria

1. A valid registration creates a user with the Affiliate role
2. Email must be unique
3. Invalid API registration data returns HTTP 422
4. Valid API login returns a Sanctum token (via `HasApiTokens::createToken()`)
5. Incorrect API credentials return HTTP 401
6. Blade registration and login pages work
7. Passwords are securely hashed
8. Login is protected by rate limiting

## Traceability

| Criterion | Design Section | Spec Requirement |
|-----------|---------------|------------------|
| 1. Registration creates Affiliate user | Web Auth Flow, API Auth Flow, Role Strategy | R1, R4, R8 |
| 2. Email must be unique | Role Strategy, Validation | R4 |
| 3. Invalid API registration returns 422 | API Auth Flow, Response Formats | R4 |
| 4. Valid API login returns Sanctum token | API Auth Flow, Token Lifecycle | R5, R13 |
| 5. Incorrect API credentials return 401 | API Auth Flow, Security | R5 |
| 6. Blade pages work | Web Auth Flow | R1, R2 |
| 7. Passwords are hashed | Password Security | R7 |
| 8. Rate limiting on login | Rate Limiting | R9 |
