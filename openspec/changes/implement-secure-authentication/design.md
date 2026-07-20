# Design: Implement Secure Authentication (KAN-9)

## 1. Web Authentication Flow

### Breeze Blade Scaffolding

Laravel Breeze Blade will be installed to provide:

- **GET /register** — Registration form page
- **POST /register** — Process registration
- **GET /login** — Login form page
- **POST /login** — Process login
- **POST /logout** — Logout (authenticated users)
- **GET /dashboard** — Redirect target after login (simple placeholder)
- Password reset routes (`/forgot-password`, `/reset-password`)
- Password confirmation route (`/confirm-password`)
- Profile management routes (`/profile`)

**Note:** Password reset and profile management are standard Breeze-generated functionality. They will be installed but not customized beyond KAN-9 scope. They serve as reference patterns and may be useful in future stories.

### Session Management

- Session driver: `database` (configured in `.env`)
- Session regeneration on login (Breeze default behavior)
- CSRF protection on all web forms (Blade `@csrf` directive)
- `guest` middleware on registration and login pages
- `auth` middleware on dashboard and profile pages

### Web Login Rate Limiting

Breeze's generated `LoginRequest` (used by `AuthenticatedSessionController`) already implements a complete login rate-limiting lifecycle internally:

1. Normalizes the submitted email.
2. Builds a limiter key from normalized email and source IP.
3. Checks whether too many failed attempts were made.
4. Returns HTTP 429 when the limit is exceeded.
5. Increments the limiter only after invalid credentials.
6. Clears the limiter after successful authentication.

**Decision:** No additional rate-limiting middleware is added to the web login route. Breeze's internal implementation is sufficient and avoids duplication.

### Customization for KAN-9

The only web authentication customization is assigning `UserRole::Affiliate` on registration. This will be handled by customizing the Breeze-generated `RegisteredUserController` to use a shared `RegisterUserAction`.

## 2. API Authentication Flow

### Routes

All API authentication routes will be nested under the existing `/api/v1` prefix in `routes/api.php`:

```
POST /api/v1/auth/register   — Public registration
POST /api/v1/auth/login      — Public login, returns Sanctum token
POST /api/v1/auth/logout     — Revoke current token (authenticated)
GET  /api/v1/auth/user       — Get authenticated user (authenticated)
```

### Route Names

```
api.v1.auth.register
api.v1.auth.login
api.v1.auth.logout
api.v1.auth.user
```

### Registration Flow

1. Client sends `POST /api/v1/auth/register` with `name`, `email`, `password`, `password_confirmation`
2. `RegisterApiRequest` validates input
3. `RegisterController` calls `RegisterUserAction`
4. `RegisterUserAction` creates user with `UserRole::Affiliate`, hashed password
5. Controller returns HTTP 201 with `UserResource`

**Decision:** API registration does NOT return a token. The client must call `/api/v1/auth/login` separately.

**Rationale:** This follows the principle of separation of concerns. Registration creates the account; login authenticates. Returning a token on registration would be convenient but couples two distinct operations. If the project later requires token-on-registration, a single boolean parameter can be added.

### Login Flow (with explicit rate limiting)

The API login flow implements an explicit authentication rate-limiting lifecycle, similar in responsibility to Breeze's `LoginRequest`. There is **no** duplicate `throttle` middleware on the login route — the controller handles the full limiter lifecycle.

1. Client sends `POST /api/v1/auth/login` with `email`, `password`, and optional `device_name`
2. `LoginApiRequest` validates input structure (email format, password present)
3. `LoginController` normalizes the submitted email (lowercase, trimmed)
4. `LoginController` builds a rate-limiter key: `normalize($email) . '|' . $request->ip()`
5. `LoginController` checks `RateLimiter::tooManyAttempts($key, 5)`:
   - If exceeded: return HTTP 429 `{"message": "Too many login attempts. Please try again in 60 seconds."}`
6. `LoginController` attempts authentication via `Auth::attempt(['email' => $email, 'password' => $password])`:
   - **On failure:** `RateLimiter::increment($key)`, return HTTP 401 `{"message": "The provided credentials are incorrect."}`
   - **On success:** `RateLimiter::clear($key)`, create Sanctum token, return HTTP 200 with token and user
7. The token is created using the resolved device name (`$request->input('device_name', 'api-client')`)
8. The plain-text token is returned **only** in this response — Sanctum stores only the hashed token internally

**Critical:** The generic "credentials are incorrect" message is identical for both wrong-email and wrong-password scenarios to prevent user enumeration.

### Logout Flow

1. Client sends `POST /api/v1/auth/logout` with `Authorization: Bearer {token}`
2. `auth:sanctum` middleware validates token
3. Current token is revoked via `$request->user()->currentAccessToken()->delete()`
4. Return HTTP 200 with success message

**Note:** Only the token used for the current request is revoked. Other tokens remain valid.

### User Flow

1. Client sends `GET /api/v1/auth/user` with `Authorization: Bearer {token}`
2. `auth:sanctum` middleware validates token
3. Return HTTP 200 with `UserResource`

## 3. Route Design

### Current `routes/api.php`

```php
Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->name('api.v1.health');
});
```

### Proposed `routes/api.php` (after KAN-9)

```php
Route::prefix('v1')->group(function () {
    // KAN-8: Health
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // KAN-9: Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [Api\V1\Auth\RegisterController::class, 'store'])
            ->middleware('throttle:api-register')
            ->name('api.v1.auth.register');

        // Login has NO throttle middleware — the controller implements
        // the full rate-limiting lifecycle internally.
        Route::post('/login', [Api\V1\Auth\LoginController::class, 'store'])
            ->name('api.v1.auth.login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [Api\V1\Auth\LogoutController::class, 'destroy'])
                ->name('api.v1.auth.logout');

            Route::get('/user', [Api\V1\Auth\UserController::class, 'show'])
                ->name('api.v1.auth.user');
        });
    });
});
```

### Web Routes

Breeze will create `routes/auth.php` containing all web authentication routes. This file is auto-loaded by Breeze's route service provider. The existing `routes/web.php` welcome route will remain.

### Throttling Ownership Summary

| Endpoint | Rate Limiter | Implementation |
|----------|-------------|----------------|
| Web `POST /login` | Breeze `LoginRequest` internal | Breeze-generated code (no modification) |
| API `POST /api/v1/auth/login` | `LoginController` explicit lifecycle | Custom controller code |
| API `POST /api/v1/auth/register` | `throttle:api-register` middleware | Named route limiter in `AppServiceProvider` |

## 4. Classes and Responsibilities

### Controllers

#### `App\Http\Controllers\Api\V1\AuthController` (Combined)

**Design deviation from original plan:** The original design specified 4 separate invokable controllers (`RegisterController`, `LoginController`, `LogoutController`, `UserController`). The implementation uses a single combined `AuthController` (~85 lines) because:
- All 4 actions are closely related (authentication lifecycle)
- The controller stays thin and cohesive — each method only maps Action results to HTTP responses
- Reduces file count without sacrificing readability
- If any method grows complex, it can be extracted later

- **Methods:** `login()`, `register()`, `logout()`, `user()`
- **Dependencies:** `RegisterUserAction`, `AuthenticateApiUserAction`, `LoginApiRequest`, `RegisterApiRequest`, `UserResource`
- **`login()`:** Delegates to `AuthenticateApiUserAction`, maps `LoginResult` to HTTP 200/401/429
- **`register()`:** Delegates to `RegisterUserAction`, returns HTTP 201 with `UserResource`
- **`logout()`:** Calls `$request->user()->currentAccessToken()->delete()`, returns HTTP 200
- **`user()`:** Returns authenticated user as `UserResource`

### Form Requests

#### `App\Http\Requests\Api\V1\RegisterApiRequest`

- **Rules:**
  - `name`: required, string, max:255
  - `email`: required, email, max:255, unique:users,email
  - `password`: required, string, min:8, confirmed
- **Authorization:** None (public endpoint)

#### `App\Http\Requests\Api\V1\LoginApiRequest`

- **Rules:**
  - `email`: required, email, max:255
  - `password`: required, string
  - `device_name`: nullable, string, max:100
- **Authorization:** None (public endpoint)
- **Note:** This Form Request validates input structure only. Rate limiting is handled by the controller, not by this request or route middleware.

### Actions

#### `App\Actions\Auth\RegisterUserAction`

- **Responsibility:** Create a new user with Affiliate role using explicit trusted assignment
- **Dependencies:** None (no RateLimiter, no Token creation)
- **Methods:** `execute(string $name, string $email, string $password): User`
- **Behavior:**
  1. Instantiate a new `User` model
  2. Use explicit `fill()` for trusted attributes (`name`, `email`, `password`)
  3. Normalize email (lowercase, trimmed)
  4. Assign `role` via direct property assignment: `$user->role = UserRole::Affiliate`
  5. Save the user
  6. Return the created User instance
- **Does NOT handle:**
  - Rate limiting (owned by `throttle:api-register` middleware)
  - Token creation (registration does not return a token per design decision)
  - HTTP responses (controller responsibility)
- **Used by:** Both `RegisteredUserController` (web) and `AuthController` (API)

### API Resources

#### `App\Http\Resources\Api\V1\UserResource`

- **Purpose:** Structure user JSON response
- **Fields:**
  ```json
  {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "affiliate"
  }
  ```
- **Excluded fields (never exposed):** `password`, `remember_token`, `token hashes`, `email_verified_at`, internal authentication fields

## 5. Sanctum Token Lifecycle

### Installation

Sanctum will be installed manually via Composer:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

This publishes the `personal_access_tokens` migration without touching `routes/api.php`.

### HasApiTokens Trait (Mandatory)

The `App\Models\User` model **must** use the `Laravel\Sanctum\HasApiTokens` trait. This is required because:

- The API login flow calls `$user->createToken(...)` which is defined on the `HasApiTokens` trait
- Without this trait, `createToken()` does not exist and authentication will fail with a method-not-found error
- This is not optional — it is a hard requirement for Sanctum token-based authentication

**User model changes:**
```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    // ...
}
```

### Configuration

- `stateful` domains: Not configured (we use Bearer Tokens, not SPA cookies)
- Token abilities: None required for KAN-9 (can be added later)
- Token expiration: Sanctum default behavior (tokens persist until revoked)

### Token Creation

```php
$deviceName = $request->input('device_name', 'api-client');
$token = $user->createToken($deviceName)->plainTextToken;
```

- The plain-text token is returned **only** in the login response
- Sanctum stores only the **hashed** token in the `personal_access_tokens` table
- The plain-text token cannot be retrieved again after creation

### Token Revocation (Logout)

```php
$request->user()->currentAccessToken()->delete();
```

Only the token used for the current request is revoked. Other tokens remain valid.

### Token Usage

```
Authorization: Bearer {plainTextToken}
```

### Testing Token Authentication

Tests must verify that a generated token can authenticate an `auth:sanctum` protected route:
```php
$user = User::factory()->create();
$token = $user->createToken('test-token')->plainTextToken;

$response = $this->withHeader('Authorization', 'Bearer ' . $token)
    ->getJson('/api/v1/auth/user');

$response->assertOk();
```

## 6. User Model and Role Migration

### Migration: Add `role` Column

A new additive migration will add a `role` column to the `users` table:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role', 20)->default('affiliate')->index()->after('email');
});
```

**Design decisions:**
- **Type:** `string('role', 20)` — portable between MySQL and SQLite, no native ENUM
- **Length:** 20 characters — sufficient for current and future role values
- **Default:** `affiliate` — every new user gets this role
- **Index:** Yes — allows efficient role-based queries in future stories
- **Position:** After `email` for logical grouping
- **Rollback:** `Schema::table('users', function (Blueprint $table) { $table->dropColumn('role'); });`

### User Model Changes

```php
use App\Enums\UserRole;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]  // 'role' NOT in fillable
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
```

**Critical protections:**
- `role` is intentionally excluded from `#[Fillable]` — mass assignment of role is impossible
- `role` is cast to `UserRole::class` — enum values are enforced at the PHP level
- The `HasApiTokens` trait is mandatory for Sanctum token operations

### Role Assignment Strategy

The role is **never** accepted from public web or API request input. It is always set server-side:

- **Web registration:** `RegisteredUserController` calls `RegisterUserAction::execute()`
- **API registration:** `RegisterController` calls `RegisterUserAction::execute()`
- **`RegisterUserAction`** uses explicit trusted assignment:
  ```php
  $user = new User();
  $user->fill([
      'name' => $data['name'],
      'email' => $data['email'],
      'password' => $data['password'],
  ]);
  $user->role = UserRole::Affiliate;
  $user->save();
  ```

Tests must prove that submitting `role=admin` in a registration request does NOT create an Admin user.

## 7. Rate Limiting Design

### API Login Rate Limiting (Explicit Lifecycle in Controller)

The API login controller implements the complete rate-limiting lifecycle. There is **no** `throttle` middleware on the login route.

**Implementation in `LoginController::store()`:**

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function store(LoginApiRequest $request): JsonResponse
{
    $email = strtolower(trim($request->string('email')));
    $key = $email . '|' . $request->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        return response()->json([
            'message' => 'Too many login attempts. Please try again in 60 seconds.',
        ], 429);
    }

    if (! Auth::attempt($request->only('email', 'password'))) {
        RateLimiter::increment($key);

        return response()->json([
            'message' => 'The provided credentials are incorrect.',
        ], 401);
    }

    RateLimiter::clear($key);

    $user = Auth::user();
    $deviceName = $request->input('device_name', 'api-client');
    $token = $user->createToken($deviceName)->plainTextToken;

    return response()->json([
        'data' => [
            'user' => new UserResource($user),
        ],
        'token' => $token,
        'token_type' => 'Bearer',
    ]);
}
```

**Configuration:**
- **Max attempts:** 5 per minute
- **Key:** `normalized_email|ip` combination
- **Window:** 1 minute (Laravel default for `perMinute`)
- **Increment:** Only after invalid credentials (not on validation failure)
- **Clear:** After successful authentication
- **HTTP 429 response:** `{"message": "Too many login attempts. Please try again in 60 seconds."}`

### API Register Rate Limiter

Registered in `AppServiceProvider::boot()`:

```php
RateLimiter::for('api-register', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});
```

Applied as route middleware: `->middleware('throttle:api-register')`

- **Max attempts:** 10 per minute per IP
- **Prevents:** Bulk account creation spam

### Web Login Rate Limiting

Breeze's generated `LoginRequest` already implements a complete login rate-limiting lifecycle internally. It follows the same pattern: normalize email, build key, check attempts, increment on failure, clear on success. **No additional middleware or custom code is needed for web login throttling.**

### Rate Limiting Ownership Summary

| Endpoint | Limiter Key | Max | Implementation |
|----------|------------|-----|----------------|
| Web `POST /login` | Breeze internal | Breeze default | Breeze `LoginRequest` |
| API `POST /api/v1/auth/login` | `normalized_email\|ip` | 5/min | `LoginController` explicit lifecycle |
| API `POST /api/v1/auth/register` | `ip` | 10/min | `throttle:api-register` middleware |

## 8. Password Security

### Hashing

- **Mechanism:** Laravel `hashed` cast on `password` attribute in User model
- **Algorithm:** bcrypt (BCRYPT_ROUNDS=12 in `.env`)
- **Guarantee:** Any value assigned to `password` is automatically hashed
- **No double hashing:** The `hashed` cast checks if the value is already hashed before hashing again

### Hidden Attributes

```php
#[Hidden(['password', 'remember_token'])]
```

- Password is never included in JSON serialization
- `UserResource` also excludes sensitive fields

### Validation Rules

- **Minimum length:** 8 characters (per Jira acceptance criteria)
- **Confirmation:** `password_confirmation` field required
- **API:** `RegisterApiRequest` enforces `min:8|confirmed`

### Testing

Tests will verify:
1. Plaintext password is never stored in database
2. Stored hash validates against original password
3. Password field is absent from API responses

## 9. Response Formats and Status Codes

### Registration (API)

**Success (201):**
```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "affiliate"
  }
}
```

**Validation Error (422):**
```json
{
  "message": "The provided data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Login (API)

**Success (200):**
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Example User",
      "email": "user@example.com",
      "role": "affiliate"
    }
  },
  "token": "1|abc123...",
  "token_type": "Bearer"
}
```

**Invalid Credentials (401):**
```json
{
  "message": "The provided credentials are incorrect."
}
```

**Note:** Generic message for both wrong email AND wrong password to prevent user enumeration.

**Throttled (429):**
```json
{
  "message": "Too many login attempts. Please try again in 60 seconds."
}
```

### Logout (API)

**Success (200):**
```json
{
  "message": "Logged out successfully."
}
```

### User (API)

**Success (200):**
```json
{
  "data": {
    "id": 1,
    "name": "Example User",
    "email": "user@example.com",
    "role": "affiliate"
  }
}
```

## 10. Test Isolation

### Database Strategy

- **PHPUnit.xml** already configures SQLite in-memory for tests (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- All tests use `RefreshDatabase` trait to ensure clean state
- No MySQL interaction during tests

### Rate Limiter Reset

Tests must reset rate-limiter state between cases to avoid flaky behavior:

```php
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('login|127.0.0.1');
    // Or clear all: RateLimiter::clear('*');
});
```

The in-memory cache driver ensures no Redis/file interference.

### Test File Structure

```
tests/
├── Feature/
│   ├── Api/
│   │   └── V1/
│   │       ├── Auth/
│   │       │   ├── RegisterTest.php
│   │       │   ├── LoginTest.php
│   │       │   ├── LogoutTest.php
│   │       │   └── UserTest.php
│   │       └── HealthTest.php
│   ├── Auth/
│   │   ├── RegistrationTest.php
│   │   └── LoginTest.php
│   └── ExampleTest.php
├── Unit/
│   ├── Actions/
│   │   └── RegisterUserActionTest.php
│   └── ExampleTest.php
├── Pest.php
└── TestCase.php
```

## 11. Breeze Frontend Asset Impact

### npm Dependencies Added by Breeze

- `@tailwindcss/forms` — Form styling plugin
- Various dev dependencies for building Blade views

### Files Modified/Created by Breeze

**Created:**
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/reset-password.blade.php`
- `resources/views/auth/confirm-password.blade.php`
- `resources/views/auth/reset-link-sent.blade.php`
- `resources/views/layouts/` — Base layout files
- `routes/auth.php` — Web auth routes
- `app/Http/Controllers/Auth/` — Web auth controllers
- `app/Http/Requests/Auth/LoginRequest.php` — Breeze login request with built-in throttling

**Modified:**
- `routes/web.php` — May add dashboard redirect
- `bootstrap/app.php` — May add route registration
- `resources/views/welcome.blade.php` — May update navigation links
- `package.json` — New npm dependencies
- `vite.config.js` — May update input paths
- `resources/css/app.css` — May add form styles

### Asset Build

After Breeze installation:
```bash
npm install
npm run build
```

### Windows/XAMPP Considerations

The project runs on Windows with XAMPP. Vite dev server and npm commands work natively on Windows. No Docker or special configuration needed.

## 12. Security Decisions

| Concern | Decision | Rationale |
|---------|----------|-----------|
| Password storage | `hashed` cast | Automatic bcrypt hashing |
| Password in responses | Hidden + excluded from Resource | Never exposed |
| Role assignment | Server-side only, `RegisterUserAction` explicit assignment | Prevents privilege escalation |
| Admin role creation | Not possible through public registration | `UserRole::Affiliate` hardcoded |
| Mass assignment | `role` not in `$fillable` | Cannot be set via mass assignment |
| CSRF | Breeze `@csrf` directive | Prevents cross-site request forgery |
| Session fixation | `session()->regenerate()` on login | Breeze default behavior |
| User enumeration | Generic "credentials are incorrect" message | Same response for wrong email and wrong password |
| Token exposure | Token returned only at creation time | Never logged or stored in responses |
| Token storage | Sanctum stores only hashed token | Plain-text returned once at creation |
| Token revocation | Only current token revoked on logout | Other tokens remain valid |
| API rate limiting | Explicit lifecycle in controller, 5 attempts/min | Prevents brute-force, clears on success |
| Web rate limiting | Breeze `LoginRequest` internal lifecycle | No duplication needed |
| Registration rate limiting | Route middleware `throttle:api-register` | Prevents spam |
| Validation | Form Requests with strict rules | Input validated before processing |
| API error format | Laravel 422/401/429 responses | Consistent, structured errors |
| Unique email | Database unique constraint | Final integrity guarantee |
| Bearer Token | Sanctum `auth:sanctum` middleware | Standard Laravel authentication |
| HasApiTokens | Mandatory on User model | Required for `createToken()` |
| Device name | Nullable, max 100, defaults to `api-client` | Identifies token origin |
| Role column | `string('role', 20)`, indexed, default `affiliate` | Portable, queryable |

## 13. Alternatives Considered and Rejected

### 1. Using `php artisan install:api`

**Rejected because:** Overwrites `routes/api.php` by default, destroying KAN-8 health endpoint. Even with `--force`, it replaces the entire file content.

**Alternative:** Manual Composer install of Sanctum.

### 2. Building Authentication Without Breeze

**Rejected because:** Jira explicitly requires Laravel Breeze with Blade. Building custom authentication would violate the acceptance criteria.

### 3. Using Livewire or React/Vue Stacks

**Rejected because:** Jira explicitly requires Blade. The project currently has minimal frontend infrastructure (Vite + Tailwind only).

### 4. Returning Token on API Registration

**Rejected because:** Separation of concerns. Registration creates the account; login authenticates. The client can call `/api/v1/auth/login` immediately after registration.

### 5. Using Fortify Without Breeze

**Rejected because:** Jira explicitly requires Breeze. Fortify is a different package.

### 6. Global Rate Limiter for API Login

**Rejected because:** A global rate limiter would block all users if one IP is malicious. A per-email+IP limiter is more targeted and fair.

### 7. Adding `role` to `$fillable`

**Rejected because:** Exposing role to mass assignment would allow any user to register as Admin. Role must always be set server-side.

### 8. Using `forceFill` or `forceCreate` for Public Registration

**Rejected because:** These methods bypass fillable guards. For public registration, the explicit `fill()` + property assignment pattern is clearer, more auditable, and preserves the intent of the fillable protection.

### 9. Throttle Middleware on API Login Route

**Rejected because:** Route-level throttle middleware would increment on every request (including validation failures), not just invalid credentials. The explicit lifecycle in the controller increments only after actual authentication failure and clears on success, which is the correct semantic.

## 14. File Inventory

### Files to Create (Actual Implementation)

| File | Purpose |
|------|---------|
| `database/migrations/2026_07_20_093520_add_role_to_users_table.php` | Add role column |
| `app/Actions/Auth/RegisterUserAction.php` | Shared registration logic (no rate limiting, no token) |
| `app/Actions/Auth/AuthenticateApiUserAction.php` | Login with explicit rate-limiting lifecycle |
| `app/DTOs/LoginResult.php` | Login result DTO (success/failed/throttled) |
| `app/Http/Controllers/Api/V1/AuthController.php` | Combined auth controller (login, register, logout, user) |
| `app/Http/Requests/Api/V1/Auth/RegisterApiRequest.php` | API registration validation |
| `app/Http/Requests/Api/V1/Auth/LoginApiRequest.php` | API login validation |
| `app/Http/Resources/Api/V1/UserResource.php` | User JSON structure (id, name, email, role) |
| `tests/Feature/Api/V1/AuthApiTest.php` | Combined API auth tests (register, login, logout, user) |
| `tests/Feature/Auth/RegistrationTest.php` | Web registration tests (updated with role + hashing) |

### Files to Modify (Actual Implementation)

| File | Change |
|------|--------|
| `composer.json` | Add `laravel/breeze` (dev) and `laravel/sanctum` |
| `routes/api.php` | Add auth routes under `/v1/auth` |
| `app/Models/User.php` | Add `HasApiTokens` trait, `role` cast |
| `app/Providers/AppServiceProvider.php` | Register `api-register` rate limiter only |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Use `RegisterUserAction` instead of `User::create()` |
| `tests/Pest.php` | Add `RefreshDatabase` trait to Feature tests |
| `database/migrations/..._add_role_to_users_table.php` | Additive migration: `string('role', 20)`, default `affiliate`, indexed |

### Files Created by Breeze (Expected)

| File | Purpose |
|------|---------|
| `routes/auth.php` | Web auth routes |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Web login |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Web registration |
| `app/Http/Controllers/Auth/PasswordResetLinkController.php` | Forgot password |
| `app/Http/Controllers/Auth/NewPasswordController.php` | Reset password |
| `app/Http/Controllers/Auth/ConfirmablePasswordController.php` | Password confirmation |
| `app/Http/Controllers/Auth/EmailVerificationPromptController.php` | Email verification |
| `app/Http/Controllers/Auth/EmailVerificationNotificationController.php` | Resend verification |
| `app/Http/Controllers/Auth/EmailVerificationController.php` | Verify email |
| `app/Http/Controllers/Auth/PasswordController.php` | Update password |
| `app/Http/Controllers/ProfileController.php` | Profile management |
| `app/Http/Requests/Auth/LoginRequest.php` | Breeze login request with built-in throttling |
| `app/Http/Requests/Auth/` | Other form request classes |
| `resources/views/auth/` | Authentication views |
| `resources/views/layouts/` | Layout views |
| `resources/views/profile/` | Profile views |
