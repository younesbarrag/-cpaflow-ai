# Spec: Manage Profile, Logout and Roles (KAN-10)

## Requirement 1: Authenticated User Can Consult Profile via API

### R1.1 — Profile consultation returns authenticated user

```
GIVEN   an authenticated user with a valid Sanctum token
WHEN    GET /api/v1/auth/user is sent with Authorization: Bearer {token}
THEN    HTTP 200 is returned
AND     response contains data.user.id, data.user.name, data.user.email, data.user.role
AND     response does NOT contain password, remember_token, or token hashes
```

### R1.2 — Unauthenticated profile consultation returns 401

```
GIVEN   no authentication token
WHEN    GET /api/v1/auth/user is sent
THEN    HTTP 401 is returned
```

## Requirement 2: Authenticated User Can Update Profile via API

### R2.1 — User can update name

```
GIVEN   an authenticated user with a valid Sanctum token
WHEN    PATCH /api/v1/profile is sent with { "name": "New Name", "email": "current@example.com" }
THEN    HTTP 200 is returned
AND     response contains data.user.name = "New Name"
AND     database users.name = "New Name"
```

### R2.2 — User can update email

```
GIVEN   an authenticated user with email "old@example.com"
WHEN    PATCH /api/v1/profile is sent with { "name": "Current Name", "email": "new@example.com" }
THEN    HTTP 200 is returned
AND     response contains data.user.email = "new@example.com"
AND     database users.email = "new@example.com"
```

### R2.3 — Current unchanged email is accepted

```
GIVEN   an authenticated user with email "user@example.com"
WHEN    PATCH /api/v1/profile is sent with { "name": "New Name", "email": "user@example.com" }
THEN    HTTP 200 is returned
AND     email is NOT changed
AND     email_verified_at is NOT reset
```

### R2.4 — Email is normalized before validation

```
GIVEN   an authenticated user
WHEN    PATCH /api/v1/profile is sent with { "name": "Test", "email": "  User@Example.COM  " }
THEN    prepareForValidation() normalizes to "user@example.com"
AND     unique validation runs against "user@example.com"
AND     HTTP 200 is returned
AND     database users.email = "user@example.com"
```

### R2.5 — Duplicate email returns 422

```
GIVEN   an authenticated user with email "user@example.com"
AND     another user exists with email "taken@example.com"
WHEN    PATCH /api/v1/profile is sent with { "name": "Test", "email": "taken@example.com" }
THEN    HTTP 422 is returned
AND     response contains validation error for email
```

### R2.6 — Invalid email returns 422

```
GIVEN   an authenticated user
WHEN    PATCH /api/v1/profile is sent with { "name": "Test", "email": "not-an-email" }
THEN    HTTP 422 is returned
AND     response contains validation error for email
```

### R2.7 — Missing name returns 422

```
GIVEN   an authenticated user
WHEN    PATCH /api/v1/profile is sent with { "email": "user@example.com" }
THEN    HTTP 422 is returned
AND     response contains validation error for name
```

### R2.8 — Unauthenticated update returns 401

```
GIVEN   no authentication token
WHEN    PATCH /api/v1/profile is sent
THEN    HTTP 401 is returned
```

### R2.9 — Role escalation through profile input is blocked

```
GIVEN   an authenticated Affiliate user
WHEN    PATCH /api/v1/profile is sent with { "name": "Test", "email": "user@example.com", "role": "admin" }
THEN    HTTP 200 is returned
AND     database users.role = "affiliate" (unchanged)
```

### R2.10 — Password is not modified

```
GIVEN   an authenticated user with a known password hash
WHEN    PATCH /api/v1/profile is sent with valid name and email
THEN    database users.password hash is unchanged
```

### R2.11 — Current token remains valid after update

```
GIVEN   an authenticated user with a valid Sanctum token
WHEN    PATCH /api/v1/profile is sent and succeeds
THEN    the same token can still authenticate GET /api/v1/auth/user
```

### R2.12 — Response excludes sensitive fields

```
GIVEN   an authenticated user
WHEN    PATCH /api/v1/profile succeeds
THEN    response does NOT contain password, remember_token, email_verified_at, or token hashes
```

### R2.13 — Email change resets email_verified_at

```
GIVEN   an authenticated user with email_verified_at set
WHEN    PATCH /api/v1/profile is sent with a new email
THEN    database users.email_verified_at = null
```

### R2.14 — Unchanged email preserves email_verified_at

```
GIVEN   an authenticated user with email_verified_at set
WHEN    PATCH /api/v1/profile is sent with the same email
THEN    database users.email_verified_at is NOT null
```

## Requirement 3: Logout Revokes Current Sanctum Token

### R3.1 — Authenticated logout succeeds

```
GIVEN   an authenticated user with a valid Sanctum token
WHEN    POST /api/v1/auth/logout is sent with Authorization: Bearer {token}
THEN    HTTP 200 is returned
AND     response contains message "Logged out successfully."
```

### R3.2 — Current token is revoked

```
GIVEN   an authenticated user with 2 tokens
WHEN    POST /api/v1/auth/logout is sent with token-1
THEN    token-1 is deleted from personal_access_tokens
AND     user has 1 remaining token
```

### R3.3 — Revoked token cannot access protected routes

```
GIVEN   a user who has logged out (token revoked)
WHEN    GET /api/v1/auth/user is sent with the revoked token
THEN    HTTP 401 is returned
```

### R3.4 — Another token remains valid

```
GIVEN   an authenticated user with token-1 and token-2
WHEN    POST /api/v1/auth/logout is sent with token-1
THEN    token-2 can still authenticate GET /api/v1/auth/user
```

### R3.5 — Unauthenticated logout returns 401

```
GIVEN   no authentication token
WHEN    POST /api/v1/auth/logout is sent
THEN    HTTP 401 is returned
```

## Requirement 4: UserRole Enum Represents Affiliate and Admin

### R4.1 — UserRole enum has correct values

```
GIVEN   the App\Enums\UserRole enum
WHEN    inspected
THEN    it contains case Affiliate = 'affiliate'
AND     it contains case Admin = 'admin'
```

### R4.2 — User model casts role to UserRole

```
GIVEN   the App\Models\User model
WHEN    a user is loaded from database
THEN    user->role is an instance of UserRole
```

### R4.3 — Registration always assigns Affiliate

```
GIVEN   a new user registration (web or API)
WHEN    the user is created
THEN    user->role = UserRole::Affiliate
```

### R4.4 — Role is not mass-assignable

```
GIVEN   the User model
WHEN    inspecting #[Fillable] attribute
THEN    'role' is NOT in the fillable array
```

## Requirement 5: Administrator Middleware Protects Routes

### R5.1 — Unauthenticated request returns 401

```
GIVEN   a route protected by auth:sanctum,admin middleware
AND     no authentication token
WHEN    the route is accessed
THEN    HTTP 401 is returned
```

### R5.2 — Affiliated user returns 403

```
GIVEN   a route protected by auth:sanctum,admin middleware
AND     an authenticated Affiliate user
WHEN    the route is accessed
THEN    HTTP 403 is returned
```

### R5.3 — Admin user passes

```
GIVEN   a route protected by auth:sanctum,admin middleware
AND     an authenticated Admin user
WHEN    the route is accessed
THEN    the request passes through (HTTP 200)
```

### R5.4 — Middleware uses UserRole enum

```
GIVEN   the EnsureUserIsAdmin middleware
WHEN    checking the user's role
THEN    it compares against UserRole::Admin (not a raw string)
```

### R5.5 — Middleware does not check authentication

```
GIVEN   the EnsureUserIsAdmin middleware
WHEN    inspecting the code
THEN    it assumes upstream auth middleware handles authentication
AND     it does not call Auth::check() or similar
```

## Requirement 6: Web Profile Behavior Consistent

### R6.1 — Web profile page accessible

```
GIVEN   an authenticated web user
WHEN    GET /profile is accessed
THEN    HTTP 200 is returned
```

### R6.2 — Web profile update works

```
GIVEN   an authenticated web user
WHEN    PATCH /profile is sent with valid name and email
THEN    the user's profile is updated
AND     redirect to /profile with status "profile-updated"
```

### R6.3 — Web profile uses shared Action

```
GIVEN   the web ProfileController::update() method
WHEN    inspected
THEN    it delegates to UpdateUserProfileAction
```

### R6.4 — Web profile role cannot be updated

```
GIVEN   an authenticated Affiliate web user
WHEN    PATCH /profile is sent with role=admin
THEN    the role remains affiliate
```
