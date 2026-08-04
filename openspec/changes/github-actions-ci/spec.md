# Spec - KAN-25: Ajouter une intégration continue avec GitHub Actions

## 1. Workflow File

| Property | Value |
|----------|-------|
| Path | `.github/workflows/ci.yml` |
| Name | `CI` |
| Trigger | `push` to `main`, `pull_request` to `main` |
| Concurrency | `ci-${{ github.ref }}`, cancel-in-progress: true |
| Permissions | `contents: read` |

## 2. Jobs

| Job | Runner | Purpose |
|-----|--------|---------|
| `backend-tests` | `ubuntu-latest` | PHP checks, MySQL migrations, Pest tests, Pint |
| `frontend-build` | `ubuntu-latest` | npm install, Vite build |

Both jobs run **in parallel** (no `needs:` dependency).

## 3. Backend Tests Job

### 3.1 Environment

| Property | Value |
|----------|-------|
| PHP Version | 8.4 |
| PHP Extensions | dom, curl, mbstring, zip, pcntl, pdo, pdo_mysql |
| Coverage | none |
| Timeout | 15 minutes |

### 3.2 MySQL Service

```yaml
services:
  mysql:
    image: mysql:8.4
    env:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: cpaflow_test
      MYSQL_USER: cpaflow
      MYSQL_PASSWORD: cpaflow
    ports:
      - 3306:3306
    options: >-
      --health-cmd="mysqladmin ping -h 127.0.0.1"
      --health-interval=10s
      --health-timeout=5s
      --health-retries=5
```

### 3.3 Steps

| # | Step | Command/Action | Expected Result |
|---|------|---------------|-----------------|
| 1 | Checkout code | `actions/checkout@v4` | Repository cloned |
| 2 | Setup PHP | `shivammathur/setup-php@v2` | PHP 8.4 + pdo_mysql installed |
| 3 | Validate composer.json | `composer validate --strict` | Exit 0 |
| 4 | Install Composer deps | `composer install --prefer-dist --no-interaction --no-progress` | vendor/ populated |
| 5 | Create .env | `cp .env.example .env` + `sed` overrides | .env points to CI MySQL |
| 6 | Generate APP_KEY | `php artisan key:generate` | APP_KEY set in .env |
| 7 | Run migrations | `php artisan migrate --force` | All migrations succeed on MySQL 8.4 |
| 8 | Run tests | `php artisan test` | 528+ tests PASS against MySQL |
| 9 | Check code style | `vendor/bin/pint --test` | Exit 0 (no files need fixing) |

### 3.4 .env Setup Detail

```bash
cp .env.example .env
sed -i 's/DB_DATABASE=cpaflow_ai/DB_DATABASE=cpaflow_test/' .env
sed -i 's/DB_USERNAME=root/DB_USERNAME=cpaflow/' .env
sed -i 's/DB_PASSWORD=/DB_PASSWORD=cpaflow/' .env
```

## 4. Frontend Build Job

### 4.1 Environment

| Property | Value |
|----------|-------|
| Node Version | 24 |
| npm Cache | Enabled via `setup-node` `cache: 'npm'` |
| Timeout | 10 minutes |

### 4.2 Steps

| # | Step | Command/Action | Expected Result |
|---|------|---------------|-----------------|
| 1 | Checkout code | `actions/checkout@v4` | Repository cloned |
| 2 | Setup Node.js | `actions/setup-node@v4` | Node 24 installed |
| 3 | Install dependencies | `npm ci` | node_modules/ populated from lockfile |
| 4 | Build frontend | `npm run build` | public/build/ created (Vite output) |

## 5. CI Environment Variables

### 5.1 Effective Test Environment

| Variable | Value | Source |
|----------|-------|--------|
| APP_ENV | testing | phpunit.xml default, not overridden |
| APP_MAINTENANCE_DRIVER | file | phpunit.xml default |
| BCRYPT_ROUNDS | 4 | phpunit.xml default |
| BROADCAST_CONNECTION | null | phpunit.xml default |
| CACHE_STORE | array | phpunit.xml default |
| DB_CONNECTION | mysql | .env (from .env.example) |
| DB_HOST | 127.0.0.1 | .env (from .env.example) |
| DB_PORT | 3306 | .env (from .env.example) |
| DB_DATABASE | cpaflow_test | .env (sed override) |
| DB_USERNAME | cpaflow | .env (sed override) |
| DB_PASSWORD | cpaflow | .env (sed override) |
| MAIL_MAILER | array | phpunit.xml default |
| QUEUE_CONNECTION | sync | phpunit.xml default |
| SESSION_DRIVER | array | phpunit.xml default |
| PULSE_ENABLED | false | phpunit.xml default |
| TELESCOPE_ENABLED | false | phpunit.xml default |
| NIGHTWATCH_ENABLED | false | phpunit.xml default |

### 5.2 Variables NOT Set in CI

| Variable | Reason |
|----------|--------|
| OPENAI_API_KEY | Tests use Prism::fake() |
| ANTHROPIC_API_KEY | Tests use Prism::fake() |
| GEMINI_API_KEY | Tests use Prism::fake() |
| REDIS_HOST | Not used (array cache/session) |

## 6. Database Strategy

| Property | Value |
|----------|-------|
| CI Engine | MySQL 8.4 (GitHub Actions service) |
| CI Database | `cpaflow_test` (ephemeral, disposable) |
| Local Engine | SQLite in-memory (phpunit.xml default) |
| Migration command | `php artisan migrate --force` |
| RefreshDatabase | Applied to all Feature tests via `tests/Pest.php` |
| Destructive commands | Not used (no migrate:fresh, reset, rollback, db:wipe) |

### 6.1 Why MySQL in CI (Not SQLite)

- Project has encountered SQLite/MySQL differences around indexes/constraints (KAN-20).
- CI should validate the real MySQL migration chain.
- Local SQLite provides fast developer feedback; CI MySQL provides production fidelity.

## 7. AI/Prism Safety

| Property | Value |
|----------|-------|
| Real AI calls in CI | Zero |
| Mechanism | `Prism::fake()` in all AI tests |
| Secrets required | None |
| QUEUE_CONNECTION | sync (acceptable because Prism is faked) |
| Queue worker | Not started |

## 8. Concurrency

```yaml
concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true
```

| Scenario | Behavior |
|----------|----------|
| PR opened | Runs CI on PR ref |
| PR updated (new push) | Cancels previous PR run |
| Push to main | Runs CI; new push cancels previous |
| Multiple PRs | Separate groups (refs/pull/N/merge), no cross-cancellation |

## 9. Permissions

```yaml
permissions:
  contents: read
```

## 10. Required Secrets

**None.** Zero GitHub Secrets for KAN-25. MySQL credentials are inline workflow values.

## 11. Job Names for Branch Protection

| Job | Check Name in GitHub |
|-----|---------------------|
| `backend-tests` | `Backend Tests` |
| `frontend-build` | `Frontend Build` |

## 12. Timeout Values

| Job | Timeout | Reasoning |
|-----|---------|-----------|
| `backend-tests` | 15 minutes | MySQL start + composer install + migrate + 528 tests + Pint |
| `frontend-build` | 10 minutes | npm ci + vite build |

## 13. Scope Exclusions

| Item | Excluded | Owner |
|------|----------|-------|
| Azure deployment | Yes | KAN-26 |
| Docker deployment | Yes | KAN-32 |
| Production secrets | Yes | KAN-26 |
| Release publishing | Yes | Future |
| Code coverage | Yes | Not in scope |
| Dependabot | Yes | Not in scope |
| Security scanning | Yes | Not in scope |
| Branch protection | Recommended | Manual GitHub config |
| Automated PR merge | Yes | Not in scope |
| Branch deletion | Yes | Not in scope |
| Redis service | Yes | Not needed (array) |
