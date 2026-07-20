# Specification: Implement Secure Authentication (KAN-9)

## Overview

This specification defines the requirements and acceptance criteria for implementing secure user registration and login in the CPAFlow AI Laravel backend. It covers Laravel Breeze Blade for web authentication and Laravel Sanctum for API Bearer Token authentication.

## Requirements

### R1: Web Registration

**Requirement:** Users must be able to register through Blade web forms.

**Scenarios:**
- SC1.1: Given a guest visits `/register`, When the page loads, Then the registration form is displayed with name, email, password, and password confirmation fields.
- SC1.2: Given a guest submits valid registration data (name, email, password, password_confirmation), When the form is processed, Then a new user is created with the `affiliate` role.
- SC1.3: Given a guest submits valid registration data, When the form is processed, Then the user is redirected to the dashboard.
- SC1.4: Given a guest submits a registration with an existing email, When the form is processed, Then a validation error is displayed and no user is created.
- SC1.5: Given a guest submits a registration with an invalid email format, When the form is processed, Then a validation error is displayed.
- SC1.6: Given a guest submits a registration with a password shorter than 8 characters, When the form is processed, Then a validation error is displayed.
- SC1.7: Given a guest submits a registration where password and password_confirmation do not match, When the form is processed, Then a validation error is displayed.
- SC1.8: Given a user is already authenticated, When they visit `/register`, Then they are redirected to the dashboard.
- SC1.9: Given a guest submits a registration with `role: "admin"` in the form data, When the user is created, Then the role is still `affiliate` (role from input is ignored).

### R2: Web Login

**Requirement:** Users must be able to log in through Blade web forms.

**Scenarios:**
- SC2.1: Given a guest visits `/login`, When the page loads, Then the login form is displayed with email and password fields.
- SC2.2: Given a registered user submits valid credentials, When the form is processed, Then the user is authenticated and redirected to the dashboard.
- SC2.3: Given a registered user submits invalid credentials, When the form is processed, Then an error message is displayed and the user is not authenticated.
- SC2.4: Given a user is already authenticated, When they visit `/login`, Then they are redirected to the dashboard.
- SC2.5: Given Breeze's `LoginRequest` is generated, Then it implements rate limiting internally (normalize email, build key, check attempts, increment on failure, clear on success). No additional throttle middleware is added to the web login route.

### R3: Web Logout

**Requirement:** Authenticated users must be able to log out.

**Scenarios:**
- SC3.1: Given an authenticated user, When they submit the logout form (POST `/logout`), Then their session is ended.
- SC3.2: Given an authenticated user logs out, When they attempt to access `/dashboard`, Then they are redirected to the login page.

### R4: API Registration

**Requirement:** Users must be able to register through the API.

**Scenarios:**
- SC4.1: Given a client sends `POST /api/v1/auth/register` with valid data (name, email, password, password_confirmation), When the request is processed, Then HTTP 201 is returned.
- SC4.2: Given a valid API registration, When the response is returned, Then the response contains the user data (id, name, email, role) wrapped in `data` without sensitive fields.
- SC4.3: Given a valid API registration, When the user is created, Then the user has the `affiliate` role.
- SC4.4: Given a client sends `POST /api/v1/auth/register` with `role: "admin"` in the body, When the request is processed, Then the user is still created with the `affiliate` role (not admin).
- SC4.5: Given a client sends `POST /api/v1/auth/register` with an existing email, When the request is processed, Then HTTP 422 is returned with a validation error containing `"email"` key.
- SC4.6: Given a client sends `POST /api/v1/auth/register` without a name, When the request is processed, Then HTTP 422 is returned.
- SC4.7: Given a client sends `POST /api/v1/auth/register` without an email, When the request is processed, Then HTTP 422 is returned.
- SC4.8: Given a client sends `POST /api/v1/auth/register` with an invalid email format, When the request is processed, Then HTTP 422 is returned.
- SC4.9: Given a client sends `POST /api/v1/auth/register` without a password, When the request is processed, Then HTTP 422 is returned.
- SC4.10: Given a client sends `POST /api/v1/auth/register` with a password shorter than 8 characters, When the request is processed, Then HTTP 422 is returned.
- SC4.11: Given a client sends `POST /api/v1/auth/register` without password_confirmation, When the request is processed, Then HTTP 422 is returned.
- SC4.12: Given a valid API registration, When the password is stored, Then the database contains a bcrypt hash, not the plaintext password.
- SC4.13: Given a valid API registration, When the response is returned, Then the response does not contain `password` or `remember_token` fields.

### R5: API Login

**Requirement:** Users must be able to log in through the API and receive a Sanctum Bearer Token. The login flow implements an explicit rate-limiting lifecycle in the controller.

**Scenarios:**
- SC5.1: Given a client sends `POST /api/v1/auth/login` with valid credentials (email, password), When the request is processed, Then HTTP 200 is returned.
- SC5.2: Given a valid API login, When the response is returned, Then the response follows the contract: `data.user` object, `token` (plain-text Sanctum token), `token_type: "Bearer"`.
- SC5.3: Given a valid API login, When the token is created, Then `$user->createToken($deviceName)` is called via the `HasApiTokens` trait on the User model.
- SC5.4: Given a valid API login, When the `device_name` input is provided, Then the token is created with that name. When omitted, the default `api-client` is used.
- SC5.5: Given a valid API login, When the response is returned, Then the `data.user` object contains id, name, email, and role.
- SC5.6: Given a valid API login token, When the client sends `GET /api/v1/auth/user` with `Authorization: Bearer {token}`, Then HTTP 200 is returned with the user data.
- SC5.7: Given a client sends `POST /api/v1/auth/login` with an incorrect password, When the request is processed, Then HTTP 401 is returned with message `"The provided credentials are incorrect."`.
- SC5.8: Given a client sends `POST /api/v1/auth/login` with a non-existent email, When the request is processed, Then HTTP 401 is returned with the same generic message as SC5.7 (no user enumeration).
- SC5.9: Given a client sends `POST /api/v1/auth/login` without an email, When the request is processed, Then HTTP 422 is returned.
- SC5.10: Given a client sends `POST /api/v1/auth/login` without a password, When the request is processed, Then HTTP 422 is returned.
- SC5.11: Given a client sends `POST /api/v1/auth/login` with an invalid email format, When the request is processed, Then HTTP 422 is returned.
- SC5.12: Given a client sends `POST /api/v1/auth/login` with `device_name` longer than 100 characters, When the request is processed, Then HTTP 422 is returned.
- SC5.13: Given the login controller implements rate limiting, When a login attempt fails, Then `RateLimiter::increment($key)` is called with key `normalized_email|ip`.
- SC5.14: Given the login controller implements rate limiting, When a login attempt succeeds, Then `RateLimiter::clear($key)` is called (failed attempt counter is reset).
- SC5.15: Given a client makes 5 failed login attempts within 1 minute for the same email+IP, When the 6th attempt is made (regardless of credentials), Then HTTP 429 is returned.
- SC5.16: Given the rate limiter response, Then the message is `"Too many login attempts. Please try again in 60 seconds."`.

### R6: API Logout

**Requirement:** Authenticated API users must be able to revoke their current token.

**Scenarios:**
- SC6.1: Given an authenticated client sends `POST /api/v1/auth/logout` with a valid Bearer Token, When the request is processed, Then HTTP 200 is returned.
- SC6.2: Given a client logs out, When they use the same token for a subsequent request, Then HTTP 401 is returned (token revoked).
- SC6.3: Given an unauthenticated client sends `POST /api/v1/auth/logout` without a token, When the request is processed, Then HTTP 401 is returned.
- SC6.4: Given a user has multiple tokens, When they logout with one token, Then only that token is revoked (other tokens remain valid).

### R7: Password Security

**Requirement:** Passwords must be securely hashed and never exposed.

**Scenarios:**
- SC7.1: Given a user registers with a password, When the password is stored, Then it is bcrypt hashed (not plaintext).
- SC7.2: Given a stored password hash, When validated against the original password, Then `Hash::check()` returns true.
- SC7.3: Given a user is serialized to JSON, Then the `password` field is not included.
- SC7.4: Given an API response contains user data, Then the response does not contain `password`, `remember_token`, token hashes, or internal authentication fields.
- SC7.5: Given a registration attempt with a password shorter than 8 characters, Then validation fails.

### R8: User Role Management

**Requirement:** Registration must always assign the Affiliate role. Public input must never be able to set the Admin role.

**Scenarios:**
- SC8.1: Given a user registers through the web, When the user is created, Then the role is `affiliate`.
- SC8.2: Given a user registers through the API, When the user is created, Then the role is `affiliate`.
- SC8.3: Given a registration request includes `role: "admin"`, When the user is created, Then the role is still `affiliate`.
- SC8.4: Given the User model's `#[Fillable]` attribute, Then `role` is NOT included (mass assignment protection).
- SC8.5: Given `RegisterUserAction` uses `new User()` + `fill()` + property assignment, Then the role is set via `$user->role = UserRole::Affiliate` (explicit trusted assignment, not mass assignment).
- SC8.6: Given the `role` column migration, Then it uses `string('role', 20)` (portable between MySQL and SQLite, no native ENUM).

### R9: Rate Limiting

**Requirement:** API login must be rate-limited to prevent brute-force attacks. The controller implements the complete rate-limiting lifecycle.

**Scenarios:**
- SC9.1: Given the login controller implements rate limiting, When a request is processed, Then the email is normalized (lowercase, trimmed) before building the limiter key.
- SC9.2: Given a client makes 5 failed login attempts within 1 minute for the same email+IP, When the 6th attempt is made, Then HTTP 429 is returned.
- SC9.3: Given a rate-limited client, When 1 minute passes, Then the client can attempt login again.
- SC9.4: Given a client makes login attempts with different email addresses from the same IP, Then each normalized email is rate-limited independently.
- SC9.5: Given a client makes login attempts with the same email from different IPs, Then each IP is rate-limited independently.
- SC9.6: Given a successful login, When `RateLimiter::clear($key)` is called, Then the failed attempt counter is reset for that key.
- SC9.7: Given the API register endpoint, Then it uses `throttle:api-register` route middleware (10 per minute per IP).
- SC9.8: Given the web login endpoint, Then Breeze's `LoginRequest` handles rate limiting internally. No additional throttle middleware is applied to the web login route.

### R10: CSRF Protection

**Requirement:** Web forms must be protected against Cross-Site Request Forgery.

**Scenarios:**
- SC10.1: Given a web registration form, When rendered, Then it contains a CSRF token field.
- SC10.2: Given a POST request to `/register` without a valid CSRF token, When processed, Then a 419 error is returned.

### R11: Session Management

**Requirement:** Web authentication must use proper session management.

**Scenarios:**
- SC11.1: Given a user logs in, When the session is created, Then `session()->regenerate()` is called (prevents session fixation).
- SC11.2: Given a user logs out, When the session is destroyed, Then the user is no longer authenticated.
- SC11.3: Given a guest tries to access `/dashboard`, When the request is processed, Then they are redirected to `/login`.

### R12: API Versioning Preservation

**Requirement:** The existing `/api/v1/health` endpoint must continue to work after authentication is implemented.

**Scenarios:**
- SC12.1: Given KAN-9 is implemented, When `GET /api/v1/health` is requested, Then HTTP 200 is returned with the original health response.
- SC12.2: Given KAN-9 is implemented, When `php artisan route:list --path=api` is executed, Then the health route is listed with the same name `api.v1.health`.
- SC12.3: Given KAN-9 is implemented, When `routes/api.php` is inspected, Then no duplicate routes exist.

### R13: HasApiTokens Trait

**Requirement:** The User model must use the `Laravel\Sanctum\HasApiTokens` trait.

**Scenarios:**
- SC13.1: Given the User model is inspected, Then it imports `Laravel\Sanctum\HasApiTokens`.
- SC13.2: Given the User model is inspected, Then `HasApiTokens` is listed in the `use` trait list.
- SC13.3: Given a user creates a token via `$user->createToken('name')`, Then a `PlainTextToken` instance is returned.
- SC13.4: Given a generated token, When used in `Authorization: Bearer {token}` header on an `auth:sanctum` route, Then the request is authenticated successfully.

### R14: Test Coverage

**Requirement:** Comprehensive Pest tests must cover all authentication scenarios.

**Scenarios:**
- SC14.1: Given the test suite runs, When all tests complete, Then all web authentication tests pass.
- SC14.2: Given the test suite runs, When all tests complete, Then all API authentication tests pass.
- SC14.3: Given the test suite runs, When all tests complete, Then the existing health endpoint test still passes (regression).
- SC14.4: Given the test suite runs, When all tests complete, Then the existing example tests still pass (regression).
- SC14.5: Given tests use SQLite in-memory, When tests run, Then no changes are made to the MySQL database.
- SC14.6: Given rate-limiting tests, When each test case runs, Then `RateLimiter::clear()` is called in `beforeEach` to reset limiter state and avoid flaky behavior.
- SC14.7: Given API login tests, When verifying token authentication, Then a token is created via `HasApiTokens::createToken()` and used to authenticate an `auth:sanctum` route.

## Exclusions

The following are explicitly excluded from this specification:

1. **Social Login:** OAuth, socialite, third-party authentication providers
2. **Two-Factor Authentication:** TOTP, SMS, backup codes
3. **Passkeys:** WebAuthn, FIDO2
4. **Email Verification Customization:** Standard Breeze scaffolding only
5. **Password Reset Customization:** Standard Breeze scaffolding only
6. **Profile Management:** Standard Breeze scaffolding only
7. **Organizations or Teams:** Multi-tenancy features
8. **Admin User Management:** CRUD for admin users
9. **Custom Permissions System:** Roles beyond Affiliate/Admin
10. **Business Features:** Offers, Campaigns, Tracking links, Clicks, Conversions
11. **AI Features:** Analysis, Content generation
12. **Infrastructure:** Docker, GitHub Actions, Azure deployment

## Acceptance Criteria

KAN-9 is complete when:

1. ✅ A valid registration creates a user with the Affiliate role (Web: R1, API: R4, Role: R8)
2. ✅ Email must be unique (Web: R1, API: R4)
3. ✅ Invalid API registration data returns HTTP 422 (R4)
4. ✅ Valid API login returns a Sanctum token via `HasApiTokens::createToken()` (R5, R13)
5. ✅ Incorrect API credentials return HTTP 401 (R5)
6. ✅ Blade registration and login pages work (R1, R2)
7. ✅ Passwords are securely hashed (R7)
8. ✅ Login is protected by rate limiting with explicit lifecycle (R9)

## Traceability

| Requirement | Jira Criterion | Design Section | Tasks |
|-------------|----------------|----------------|-------|
| R1: Web Registration | Criteria 1, 2, 6 | Web Auth Flow | Section 2, 8 |
| R2: Web Login | Criteria 6 | Web Auth Flow | Section 2, 8 |
| R3: Web Logout | Criteria 6 | Web Auth Flow | Section 2 |
| R4: API Registration | Criteria 1, 2, 3 | API Auth Flow | Section 6, 7 |
| R5: API Login | Criteria 4, 5 | API Auth Flow, Token Lifecycle | Section 7 |
| R6: API Logout | — | API Auth Flow | Section 7 |
| R7: Password Security | Criteria 7 | Password Security | Section 5, 6 |
| R8: User Role | Criteria 1 | Role Strategy | Section 4, 5, 6 |
| R9: Rate Limiting | Criteria 8 | Rate Limiting | Section 7, 9 |
| R10: CSRF | — | Security | Section 2 |
| R11: Session Management | — | Web Auth Flow | Section 2 |
| R12: API Versioning | — | Route Design | Section 3, 7 |
| R13: HasApiTokens | Criteria 4 | Token Lifecycle | Section 4, 5 |
| R14: Test Coverage | — | Test Strategy | Section 11, 12 |
