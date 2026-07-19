# Design: Initialize CPAFlow Foundation

## Repository State

**Git Branch:** `feature/KAN-8-project-foundation`
**Working Tree:** Clean (no uncommitted changes)

**Technology Stack:**
- Laravel: 13.19.0
- PHP: 8.4.20
- Composer: 2.9.7
- MySQL Database: `cpaflow_ai` (created, migrations pending)

**Current Structure:**
- `routes/web.php` exists with default welcome route
- `routes/api.php` does NOT exist
- `bootstrap/app.php` configures web routes only
- Default Laravel migrations exist in `database/migrations/`
- Tests use PHPUnit-style syntax
- No `app/Actions`, `app/Services`, or `app/Enums` directories

## Target Architecture

### Directory Structure

```
app/
├── Actions/                    # Business use cases (one class per action)
├── Enums/                      # String-backed enums
├── Http/
│   └── Controllers/
│       └── Api/
│           └── V1/
│               └── HealthController.php
├── Services/                   # Reusable calculations and integrations
├── Models/
├── Providers/
routes/
├── api.php                     # API routes (versioned)
├── web.php                     # Web routes
├── console.php                 # Console commands
```

### API Versioning Strategy

**Decision:** Use `routes/api.php` with manual registration in `bootstrap/app.php`

**Rationale:**
- Laravel 13 supports manual API route registration
- Avoids `php artisan install:api` which installs Sanctum
- Maintains clean separation between API versions
- Allows future addition of `/api/v2` without breaking changes

**Implementation:**
1. Create `routes/api.php` manually
2. Add `api: __DIR__.'/../routes/api.php'` to `withRouting()` in `bootstrap/app.php`
3. Prefix routes with `/v1` in `routes/api.php`

### MySQL Verification Strategy

**Pre-migration Steps:**
1. Clear cached configuration: `php artisan config:clear`
2. Verify database connection: `php artisan db:show`
3. Check migration status: `php artisan migrate:status`

**Migration Execution:**
1. Run only default Laravel migrations: `php artisan migrate`
2. Verify migration status after execution
3. Avoid destructive operations (no `migrate:fresh` or `migrate:rollback`)

**Safety Measures:**
- Use `--force` flag only in production
- Verify database name matches configuration
- Check for existing migration table before execution

### Health Endpoint Design

**Route:** `GET /api/v1/health`

**Response Structure:**
```json
{
  "status": "ok",
  "service": "CPAFlow API",
  "version": "v1",
  "timestamp": "2026-07-19T06:30:00.000000Z"
}
```

**Implementation:**
- Invokable controller: `App\Http\Controllers\Api\V1\HealthController`
- No Action or Service classes (no business logic)
- No authentication required (public endpoint)
- Named route: `api.v1.health`

**Security Considerations:**
- No credentials, APP_KEY, or environment variables exposed
- No stack traces or sensitive configuration
- No database queries (stateless endpoint)

### Enum Strategy

**Type:** String-backed enums (PHP 8.1+)

**Enums to Create:**

| Enum | Values |
|------|--------|
| `UserRole` | `affiliate`, `admin` |
| `OfferStatus` | `draft`, `active`, `suspended`, `archived` |
| `CampaignStatus` | `draft`, `active`, `suspended` |
| `ConversionStatus` | `pending`, `approved`, `rejected` |
| `AiProcessStatus` | `pending`, `processing`, `completed`, `failed` |

**Location:** `app/Enums/`

**Usage:**
- Database columns: `varchar` or `string` type
- Model casting: `'column' => OfferStatus::class`
- Form validation: `Rule::enum(OfferStatus::class)`

**Note:** No Models or business migrations for these Enums in KAN-8.

### Actions and Services Strategy

**Actions:**
- Location: `app/Actions/`
- Purpose: One specific business use case per class
- Naming: `{Verb}{Noun}.php` (e.g., `CreateOffer.php`)
- Interface: None required (avoid unnecessary abstractions)
- Dependencies: Inject via constructor

**Services:**
- Location: `app/Services/`
- Purpose: Reusable calculations or external integrations
- Naming: `{Noun}Service.php` (e.g., `ConversionService.php`)
- Interface: None required unless multiple implementations needed
- Dependencies: Inject via constructor

**Architecture Rules:**
1. Controllers remain thin (coordinate HTTP input/output only)
2. Form Requests handle validation
3. Policies and Middleware handle authorization
4. Actions implement business logic
5. Services contain reusable logic
6. API Resources structure responses
7. Database constraints protect data integrity
8. No business logic in controllers

### Pest Strategy

**Installation:**
- Add `pestphp/pest` as dev dependency
- Add `pestphp/pest-plugin-laravel` for Laravel integration
- Create `Pest.php` configuration file
- Convert existing PHPUnit tests to Pest syntax

**Test Structure:**
```
tests/
├── Feature/
│   └── Api/
│       └── V1/
│           └── HealthTest.php
├── Unit/
├── TestCase.php
└── Pest.php
```

**Health Endpoint Test:**
```php
test('health endpoint returns 200 with correct structure', function () {
    $response = $this->getJson('/api/v1/health');
    
    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'service' => 'CPAFlow API',
            'version' => 'v1',
        ])
        ->assertJsonStructure(['timestamp']);
});
```

**Database Strategy:**
- Use in-memory SQLite for tests (configured in phpunit.xml)
- Use `RefreshDatabase` trait where needed
- Avoid testing against MySQL in unit tests

### Pint Strategy

**Configuration:**
- Use default Laravel Pint configuration (no `pint.json` needed)
- Verify compliance with: `./vendor/bin/pint --test`
- Fix violations with: `./vendor/bin/pint`

**Verification Steps:**
1. Run Pint in test mode to check compliance
2. Fix any violations before committing
3. Include Pint check in CI pipeline (future)

### Security Considerations

1. **Secrets:** No credentials, API keys, or APP_KEY in responses
2. **Error Handling:** JSON responses for API errors (configured in bootstrap/app.php)
3. **Debug Mode:** Disabled in production (APP_DEBUG=false)
4. **Database:** Use environment variables, not hardcoded credentials
5. **Health Endpoint:** Stateless, no sensitive information exposed

### Alternatives Considered and Rejected

1. **Using `php artisan install:api`:**
   - Rejected because it installs Sanctum automatically
   - Sanctum belongs to KAN-9

2. **Using `/api/v1` prefix in each route file:**
   - Rejected because it fragments route organization
   - Better to have dedicated `routes/api.php` with prefix

3. **Creating a base controller for API v1:**
   - Rejected as unnecessary abstraction
   - Laravel's default Controller is sufficient

4. **Using Interfaces for Actions/Services:**
   - Rejected unless multiple implementations needed
   - Follows Laravel convention of avoiding unnecessary abstractions

### Separation Between KAN-8 and KAN-9

**KAN-8 Foundation:**
- API versioning structure
- Health endpoint
- Testing infrastructure
- Architectural patterns
- Database migrations (users table)

**KAN-9 Authentication:**
- Laravel Sanctum installation
- Authentication routes (`/api/v1/auth/*`)
- Registration, Login, Logout endpoints
- Bearer token handling
- Password reset functionality
- User profile management
- Auth middleware and policies

**Handoff:**
KAN-8 provides the infrastructure that KAN-9 builds upon. The API versioning, testing patterns, and architectural conventions established in KAN-8 will be followed in KAN-9.
