# Design: Manage Profile, Logout and Roles (KAN-10)

## 1. Current-State Analysis

### What KAN-9 Already Provides

| Component | File | KAN-10 Relevance |
|-----------|------|-------------------|
| User model | `app/Models/User.php` | HasApiTokens, UserRole cast, role NOT fillable |
| UserRole enum | `app/Enums/UserRole.php` | Affiliate, Admin values |
| AuthController | `app/Http/Controllers/Api/V1/AuthController.php` | `user()` serves as profile consultation |
| UserResource | `app/Http/Resources/Api/V1/UserResource.php` | Returns id, name, email, role |
| Web ProfileController | `app/Http/Controllers/ProfileController.php` | edit/update/destroy |
| Web ProfileUpdateRequest | `app/Http/Requests/ProfileUpdateRequest.php` | Validates name, email (unique ignore self) |
| API logout | `AuthController::logout()` | Revokes current token only |
| API user | `AuthController::user()` | Returns UserResource for authenticated user |

### What Is Missing

| Component | Purpose |
|-----------|---------|
| API profile update endpoint | PATCH /api/v1/profile |
| UpdateProfileRequest (API) | Form Request for API profile validation |
| UpdateUserProfileAction | Shared business logic for profile updates |
| EnsureUserIsAdmin middleware | Administrator route protection |
| Middleware alias registration | `admin` alias in bootstrap/app.php |
| API profile tests | Verify API profile update behavior |
| Admin middleware tests | Verify role-based access control |

### Key Observation: User Model Does NOT Implement MustVerifyEmail

The `App\Models\User` model does not implement `MustVerifyEmail`. The `email_verified_at` column exists in the database (from default Laravel migration) and is cast to `datetime`, but email verification is not enforced on the model level.

- The dashboard route uses `middleware(['auth', 'verified'])` but this only checks if `email_verified_at` is not null — it does not trigger email verification flows.
- The Breeze `VerifyEmailController` and related routes exist but are not actively enforced by the model.

**Decision:** Do not introduce `MustVerifyEmail` in KAN-10. The existing behavior is:
- When email changes: set `email_verified_at` to null (current web behavior).
- When email is unchanged: `email_verified_at` remains as-is.
- This is consistent with the web ProfileController behavior already in place.

## 2. Route Strategy

### Existing Routes (no changes)

```
GET  /api/v1/auth/user       → AuthController::user()    [auth:sanctum]
POST /api/v1/auth/logout     → AuthController::logout()  [auth:sanctum]
```

### New Route

```
PATCH /api/v1/profile        → Api\V1\ProfileController::update()  [auth:sanctum]
```

**Route name:** `api.v1.profile.update`

**Justification:**
- `GET /api/v1/auth/user` already serves as profile consultation — no duplicate needed.
- `PATCH /api/v1/profile` is a distinct resource path for profile management.
- Using `PATCH` (not `PUT`) allows partial updates.
- Placed outside the `/auth` prefix because it is not an authentication action — it is a profile management action.

### Web Routes (no changes to routes)

The existing web profile routes remain as-is:
- `GET /profile` → `ProfileController::edit`
- `PATCH /profile` → `ProfileController::update`
- `DELETE /profile` → `ProfileController::destroy`

### Final `routes/api.php` After KAN-10

```php
Route::prefix('v1')->group(function () {
    // Health
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:api-register')
        ->name('api.v1.auth.register');

    // Auth (authenticated)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/auth/user', [AuthController::class, 'user'])->name('api.v1.auth.user');

        // Profile
        Route::patch('/profile', [Api\V1\ProfileController::class, 'update'])
            ->name('api.v1.profile.update');
    });
});
```

## 3. Shared Action Strategy

### New Class: `App\Actions\Profile\UpdateUserProfileAction`

**Responsibility:** Centralize profile-update business logic for both web and API.

**Method signature:**
```php
public function execute(User $user, array $data): User
```

**Behavior:**
1. Normalize email (trim + lowercase).
2. Detect whether email actually changed (`$user->email !== $normalizedEmail`).
3. Fill only `name` and `email` on the User model.
4. If email changed: set `email_verified_at` to null.
5. Save and return the User.

**Must NOT:**
- Return HTTP responses.
- Authorize requests.
- Update `role`, `password`, `remember_token`, or `tokens`.
- Accept `role` from input.

### Why a Separate Action (Not Inline in Controllers)

- Avoids duplicating the same logic in `ProfileController::update()` (web) and `Api\V1\ProfileController::update()` (API).
- Ensures email normalization and `email_verified_at` handling are consistent.
- Makes the business logic independently testable.
- Follows the same pattern as `RegisterUserAction` (shared by web and API).

### Web ProfileController Adaptation

The existing `ProfileController::update()` will be adapted to use `UpdateUserProfileAction`:

```php
public function update(ProfileUpdateRequest $request, UpdateUserProfileAction $action): RedirectResponse
{
    $action->execute($request->user(), $request->validated());

    return Redirect::route('profile.edit')->with('status', 'profile-updated');
}
```

This replaces the current inline `$request->user()->fill(...)` + `isDirty('email')` logic.

## 4. Validation

### API Form Request: `App\Http\Requests\Api\V1\Profile\UpdateProfileRequest`

```php
public function authorize(): bool
{
    return true; // auth:sanctum middleware handles authentication
}

public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'email:rfc',
            'max:255',
            Rule::unique('users', 'email')->ignore($this->user()->id),
        ],
    ];
}
```

**Key points:**
- `email:rfc` (not `email:dns`) — consistent with existing API requests, avoids DNS resolution failures in test environments.
- `Rule::unique()->ignore($this->user()->id)` — allows the user to submit their current email without triggering a duplicate error.
- `role` is NOT in the rules — mass assignment protection + no input field.
- `password` is NOT in the rules — password updates remain in Breeze scope.

### Email Normalization

**Correction (mandatory):** Email must be normalized **before validation** in both API and Web Form Requests using `prepareForValidation()`. This ensures:
- The unique-email validation operates on the normalized email.
- Case-variant duplicates are blocked at the validation layer.
- Consistent behavior between SQLite tests and MySQL.

**Form Request normalization (prepareForValidation):**
```php
protected function prepareForValidation(): void
{
    $this->merge([
        'email' => strtolower(trim($this->input('email', ''))),
    ]);
}
```

**Action normalization (defensive):** The Action also normalizes email before persistence as a safety net:
- `strtolower(trim($email))`

**Scenario:** User submits `  Test@Example.COM  ` → `prepareForValidation()` normalizes to `test@example.com` → unique validation runs against `test@example.com` → Action defensively normalizes again → saved in database.

### What Happens When User Submits Their Current Email

- Form Request: `Rule::unique('users', 'email')->ignore($this->user()->id)` passes (email belongs to self).
- Action: `$user->email !== $normalizedEmail` is false → `email_verified_at` NOT reset.
- Result: Name is updated, email stays the same, `email_verified_at` preserved.

## 5. Email Verification Behavior

**Current state:** User model does NOT implement `MustVerifyEmail`. Email verification routes exist (Breeze) but are not enforced by the model.

**KAN-10 behavior:**
- When email changes: `email_verified_at` is set to `null` by `UpdateUserProfileAction`.
- When email is unchanged: `email_verified_at` remains as-is.
- No new verification notification is sent in KAN-10 scope.
- This matches the existing web ProfileController behavior.

**Future consideration:** If `MustVerifyEmail` is added later, the Action's behavior (reset `email_verified_at` on change) is already correct.

## 6. API Response Contracts

### Consult Current User (existing)

```
GET /api/v1/auth/user
Authorization: Bearer {token}

HTTP 200

{
  "data": {
    "user": {
      "id": 1,
      "name": "Example User",
      "email": "user@example.com",
      "role": "affiliate"
    }
  }
}
```

### Update Profile (new)

```
PATCH /api/v1/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "email": "updated@example.com"
}

HTTP 200

{
  "data": {
    "user": {
      "id": 1,
      "name": "Updated Name",
      "email": "updated@example.com",
      "role": "affiliate"
    }
  }
}
```

### Validation Failure

```
HTTP 422

{
  "message": "The provided data was invalid.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

### Unauthenticated

```
HTTP 401
```

### Response Never Exposes

- `password`
- `remember_token`
- `email_verified_at`
- `personal_access_tokens` hashes
- plain-text tokens
- internal authentication fields

## 7. Logout Verification

The existing `POST /api/v1/auth/logout` endpoint is already correct:
- Revokes only `$request->user()->currentAccessToken()`.
- Does NOT revoke all tokens.

KAN-10 plans verification tests proving:
- Current token is revoked (count decreases by 1).
- Revoked token cannot access protected routes.
- Another token from the same user remains valid.

No changes to the logout implementation are needed.

## 8. Role Security

### Current Protections (KAN-9, verified)

| Protection | Evidence |
|------------|----------|
| `role` NOT in `#[Fillable]` | User model: `#[Fillable(['name', 'email', 'password'])]` |
| `role` cast to enum | `'role' => UserRole::class` |
| Registration assigns Affiliate server-side | `RegisterUserAction::execute()` hardcodes `UserRole::Affiliate` |
| API registration ignores `role` input | Test: `role=admin` in request → user still gets `affiliate` |

### KAN-10 Additional Protection

- `UpdateProfileRequest` does NOT include `role` in validation rules.
- `UpdateUserProfileAction` does NOT accept or modify `role`.
- Test proves that submitting `role=admin` via `PATCH /api/v1/profile` does not change the user's role.

## 9. Administrator Middleware

### New Class: `App\Http\Middleware\EnsureUserIsAdmin`

```php
namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->role !== UserRole::Admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
```

**Key points:**
- Assumes `auth:sanctum` (or `auth`) middleware runs first — does not check authentication itself.
- Compares using `UserRole::Admin` enum (type-safe).
- Returns HTTP 403 for authenticated non-admin users.
- Returns nothing for unauthenticated users (upstream middleware handles 401).

### Middleware Alias Registration

In `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void
{
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    ]);
})
```

### Expected Future Usage

```php
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Admin routes (KAN-22)
});
```

### Order: auth:sanctum → admin

| Scenario | auth:sanctum | admin | Result |
|----------|-------------|-------|--------|
| No token | 401 | — | HTTP 401 |
| Affiliate token | passes | fails | HTTP 403 |
| Admin token | passes | passes | Request allowed |

### Testing Strategy

No permanent admin endpoints will be created in KAN-10. Tests will register temporary test-only routes:

```php
Route::middleware(['auth:sanctum', 'admin'])->get('/test-admin', fn () => response()->json(['ok' => true]));
```

This avoids introducing placeholder admin routes that serve no business purpose.

## 10. Middleware vs. Policy Decision

| Concern | Mechanism | Rationale |
|---------|-----------|-----------|
| Administrator area access | Middleware (`admin`) | Guards entire route groups, not specific model instances |
| Profile update ownership | Authenticated user + `auth:sanctum` | User can only update their own profile (enforced by `$request->user()`) |
| Specific model authorization | Policy (future) | Not needed for KAN-10 — no per-model authorization required |

**Decision:** No `UserPolicy` is created in KAN-10. The profile update is scoped to the authenticated user via `$request->user()` — there is no cross-user authorization scenario. A Policy would be over-engineering for this ticket.

## 11. Security Summary

| Concern | Implementation |
|---------|----------------|
| API auth | `auth:sanctum` on all profile routes |
| Web CSRF | Breeze `@csrf` directive (existing) |
| Own-profile only | `$request->user()` scoped update |
| Role escalation blocked | `role` not in Form Request rules, not in Action |
| Unique email | `Rule::unique()->ignore($this->user()->id)` |
| Email normalization | `strtolower(trim())` in Action |
| Passwords not exposed | `UserResource` excludes sensitive fields |
| Admin middleware | `UserRole::Admin` enum comparison |
| Affiliate gets 403 | Middleware returns 403 for non-admin |
| Unauthenticated gets 401 | `auth:sanctum` returns 401 before admin check |
| No secrets logged | No logging of tokens, passwords, or keys |
| Logout revokes current only | Existing `currentAccessToken()->delete()` |

## 12. Alternatives Considered and Rejected

### 1. Creating a Separate GET /api/v1/profile Endpoint

**Rejected because:** `GET /api/v1/auth/user` already returns the authenticated user via `UserResource`. Creating a duplicate endpoint would violate DRY and confuse API consumers.

### 2. Using PUT Instead of PATCH for Profile Update

**Rejected because:** PUT implies a full replacement of the resource. PATCH allows partial updates (e.g., updating only the name without submitting the email).

### 3. Creating a UserPolicy for Profile Updates

**Rejected because:** The profile update is inherently scoped to the authenticated user (`$request->user()`). There is no cross-user authorization scenario in KAN-10. A Policy would add complexity without value.

### 4. Resetting All Tokens on Email Change

**Rejected because:** KAN-9 design explicitly states that logout revokes only the current token. Resetting all tokens on email change would be a security policy decision outside KAN-10 scope.

### 5. Adding MustVerifyEmail in KAN-10

**Rejected because:** This is a behavioral change that affects registration and login flows. It belongs in a dedicated story, not in a profile-management ticket.

### 6. Placing Profile Route Under /auth Prefix

**Rejected because:** Profile management is not an authentication action. The `/auth` prefix is reserved for login, register, logout, and user retrieval. Profile update is a distinct resource.

## 13. File Inventory

### Files to Create

| File | Purpose |
|------|---------|
| `app/Actions/Profile/UpdateUserProfileAction.php` | Shared profile-update business logic |
| `app/Http/Controllers/Api/V1/ProfileController.php` | API profile update controller |
| `app/Http/Requests/Api/V1/Profile/UpdateProfileRequest.php` | API profile validation |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Administrator middleware |
| `tests/Feature/Api/V1/ProfileApiTest.php` | API profile update tests |
| `tests/Feature/Middleware/AdminMiddlewareTest.php` | Admin middleware tests |

### Files to Modify

| File | Change |
|------|--------|
| `routes/api.php` | Add `PATCH /profile` route |
| `bootstrap/app.php` | Register `admin` middleware alias |
| `app/Http/Controllers/ProfileController.php` | Use `UpdateUserProfileAction` |
| `tests/Feature/ProfileTest.php` | Add role-security test |

### Files Reused (no changes)

| File | Role |
|------|------|
| `app/Enums/UserRole.php` | Affiliate/Admin enum |
| `app/Models/User.php` | HasApiTokens, role cast |
| `app/Http/Resources/Api/V1/UserResource.php` | JSON response structure |
| `app/Http/Controllers/Api/V1/AuthController.php` | user() as profile consultation |
| `tests/Feature/Api/V1/AuthApiTest.php` | Existing logout tests |
