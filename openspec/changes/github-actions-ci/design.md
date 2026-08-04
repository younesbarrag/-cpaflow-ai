# Design - KAN-25: Ajouter une intégration continue avec GitHub Actions

## 1. Evidence Base

All decisions below are derived from repository inspection, not assumption.

### Repository State

| Item | Evidence | Source |
|------|----------|--------|
| No `.github` directory | glob `**/.github/**` returned no files | Repository inspection |
| PHP constraint | `"php": "^8.3"` | `composer.json:9` |
| Laravel version | `"laravel/framework": "^13.8"` | `composer.json:10` |
| Local PHP | PHP 8.4.20 | `php --version` |
| Local Node | v24.11.1 | `node --version` |
| package-lock.json | Exists, lockfileVersion 3 | File system |
| .npmrc | `ignore-scripts=true`, `audit=true` | `.npmrc` |
| Test DB config | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` | `phpunit.xml:26-27` |
| Cache in tests | `CACHE_STORE=array` | `phpunit.xml:25` |
| Session in tests | `SESSION_DRIVER=array` | `phpunit.xml:31` |
| Queue in tests | `QUEUE_CONNECTION=sync` | `phpunit.xml:30` |
| AI tests | All use `Prism::fake()` — 16 occurrences | `AiAnalysisApiTest.php` |
| Test baseline | 528/528 PASS | Project record |
| Pest config | `RefreshDatabase` on Feature tests | `tests/Pest.php:17-19` |
| Composer scripts | `test`, `setup`, `dev` | `composer.json:38-72` |
| npm scripts | `build`, `dev` | `package.json:6-8` |
| composer validate --strict | Passes cleanly | `composer validate --strict` executed |

### .env.example Database Section

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaflow_ai
DB_USERNAME=root
DB_PASSWORD=
```

### phpunit.xml Environment Block

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="APP_MAINTENANCE_DRIVER" value="file"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="BROADCAST_CONNECTION" value="null"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="DB_URL" value=""/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="PULSE_ENABLED" value="false"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
    <env name="NIGHTWATCH_ENABLED" value="false"/>
</php>
```

## 2. How phpunit.xml and CI MySQL Coexist

### The Override Mechanism

PHPUnit's `<env>` elements (without `force="append"`) act as **defaults**. They set environment variables via `putenv()` only if the variable is not already set in the process environment.

Laravel's Dotenv loader (invoked during `bootstrap/app.php`) calls `putenv()` for each variable found in the `.env` file, **overriding** any previously set values.

### Precedence Chain in CI

```
1. PHPUnit reads phpunit.xml <env> elements → putenv("DB_CONNECTION=sqlite")
2. Laravel bootstrap loads .env → putenv("DB_CONNECTION=mysql")
3. Final value: mysql (Laravel's .env wins)
```

### Precedence Chain Locally (Developer Machine)

```
1. PHPUnit reads phpunit.xml <env> elements → putenv("DB_CONNECTION=sqlite")
2. Laravel bootstrap loads .env (developer's local file) → putenv("DB_CONNECTION=mysql")
3. Final value: mysql (developer's .env wins)
4. BUT: developer's .env is gitignored — only phpunit.xml matters for committed code
```

### Why This Works

- **CI:** `.env` is generated from `.env.example` which has `DB_CONNECTION=mysql`. Laravel loads it, overriding phpunit.xml's SQLite default. Tests run against MySQL.
- **Local:** Developer has their own `.env` with `DB_CONNECTION=mysql`. Laravel loads it, overriding phpunit.xml. Tests run against local MySQL.
- **Local (no .env):** If a developer has no `.env` file, phpunit.xml's `DB_CONNECTION=sqlite` takes effect. Tests run against SQLite :memory:.

### CI .env Generation

```bash
cp .env.example .env
```

The `.env.example` already contains `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, etc. We override only the database name and credentials via workflow environment variables:

```yaml
env:
  DB_DATABASE: cpaflow_test
  DB_USERNAME: cpaflow
  DB_PASSWORD: cpaflow
```

These are set as **OS-level environment variables** in the workflow, which means they are present in the process environment BEFORE Laravel's Dotenv loader runs. When Dotenv processes the `.env` file, it calls `putenv("DB_DATABASE=cpaflow_ai")` — but this **overrides** the workflow value.

**Correction:** To ensure the workflow env vars win, we must write them into the `.env` file itself after copying from `.env.example`. The `sed` or shell replacement approach:

```bash
cp .env.example .env
sed -i 's/DB_DATABASE=cpaflow_ai/DB_DATABASE=cpaflow_test/' .env
sed -i 's/DB_USERNAME=root/DB_USERNAME=cpaflow/' .env
sed -i 's/DB_PASSWORD=/DB_PASSWORD=cpaflow/' .env
```

This way, when Laravel loads `.env`, it reads the CI-specific values directly from the file.

### Evidence: No phpunit.xml Modification Required

The smallest safe change is: **none to phpunit.xml**. The `.env` file generated in CI already provides MySQL configuration. phpunit.xml's SQLite defaults are overridden by Laravel's `.env` loading. Local developers are unaffected because their `.env` is gitignored.

## 3. Trigger Strategy

```yaml
on:
  push:
    branches: [main]
  pull_request:
    branches: [main]
```

### Exact Trigger Behavior

| Scenario | Event | CI Runs? |
|----------|-------|----------|
| Feature branch push, NO open PR | `push` to `feature-branch` | **No** — not matching `branches: [main]` |
| Open PR targeting main | `pull_request` opened | **Yes** |
| Push to open PR branch | `pull_request` synchronize | **Yes** |
| Merge/direct push to main | `push` to `main` | **Yes** |
| Multiple PRs from different branches | Separate `pull_request` events | **Yes** — each独立 |

Feature-branch pushes without an open PR do **not** trigger CI. This is intentional: CI runs on PRs and merges to main, not on every branch push.

## 4. PHP Version Strategy

### Decision: PHP 8.4 only (no matrix)

- `composer.json` requires `^8.3`.
- Local uses PHP 8.4.20.
- Single version = faster feedback, simpler debugging.
- Matrix can be added later if needed.

## 5. Database Strategy

### Decision: MySQL 8.4 in CI, SQLite locally

| Environment | Database | Reason |
|-------------|----------|--------|
| Local | SQLite :memory: | Speed, zero setup |
| CI | MySQL 8.4 service | Fidelity with production MySQL engine |

### Why MySQL in CI

- The project has encountered SQLite/MySQL differences around indexes and constraints during KAN-20.
- CI should validate the real MySQL migration chain and test suite.
- Local SQLite provides fast feedback; CI MySQL provides complementary fidelity.

### MySQL Service Configuration

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

### Migration Command

```bash
php artisan migrate --force
```

Runs against `cpaflow_test` MySQL database. Validates the complete MySQL migration chain. `--force` required for non-interactive mode. The CI database is disposable but normal migration behavior is exercised.

### No Destructive Migration Commands

Do NOT use: `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`.

## 6. Job Architecture

### Two Separate Jobs (Not One Combined)

```
backend-tests                  frontend-build
├── composer validate --strict ├── npm ci
├── composer install           └── npm run build
├── cp .env.example .env
├── sed (DB overrides)
├── php artisan key:generate
├── php artisan migrate --force
├── php artisan test
└── vendor/bin/pint --test
```

Rationale:
- **Clear failure signals:** "Backend Tests failed" vs "Frontend Build failed".
- **Independent execution:** Parallel, reducing total CI time.
- **Branch protection:** Can require both checks independently.

## 7. Backend Job Steps

```yaml
steps:
  - name: Checkout code
    uses: actions/checkout@v4

  - name: Setup PHP
    uses: shivammathur/setup-php@v2
    with:
      php-version: '8.4'
      extensions: dom, curl, mbstring, zip, pcntl, pdo, pdo_mysql
      coverage: none

  - name: Validate composer.json
    run: composer validate --strict

  - name: Install Composer dependencies
    run: composer install --prefer-dist --no-interaction --no-progress

  - name: Create environment file
    run: |
      cp .env.example .env
      sed -i 's/DB_DATABASE=cpaflow_ai/DB_DATABASE=cpaflow_test/' .env
      sed -i 's/DB_USERNAME=root/DB_USERNAME=cpaflow/' .env
      sed -i 's/DB_PASSWORD=/DB_PASSWORD=cpaflow/' .env

  - name: Generate application key
    run: php artisan key:generate

  - name: Run database migrations
    run: php artisan migrate --force

  - name: Run tests
    run: php artisan test

  - name: Check code style
    run: vendor/bin/pint --test
```

### Step Justifications

| Step | Why |
|------|-----|
| `php-version: '8.4'` | Matches local PHP version |
| `extensions: pdo_mysql` | Required for MySQL connectivity |
| `composer validate --strict` | Passes; enforces full composer.json validation |
| `--prefer-dist` | Faster than `--prefer-source` |
| `--no-interaction` | Prevents prompts hanging CI |
| `sed` overrides | Ensure .env points to CI MySQL, not .env.example defaults |
| `key:generate` | APP_KEY required for encryption |
| `migrate --force` | Validates MySQL migration chain |
| `php artisan test` | Runs full Pest suite against MySQL |
| `pint --test` | Read-only style check |

## 8. Frontend Job Steps

```yaml
steps:
  - name: Checkout code
    uses: actions/checkout@v4

  - name: Setup Node.js
    uses: actions/setup-node@v4
    with:
      node-version: '24'
      cache: 'npm'

  - name: Install dependencies
    run: npm ci

  - name: Build frontend
    run: npm run build
```

## 9. Composer Command

```bash
composer install --prefer-dist --no-interaction --no-progress
```

- Uses `composer.lock` (not `composer update`).
- No manual `vendor/` caching — fresh install from lockfile each run.

## 10. npm Command

```bash
npm ci
```

- Uses `package-lock.json` (lockfileVersion 3).
- `.npmrc` has `ignore-scripts=true`.

## 11. AI/Prism Safety

| Property | Value |
|----------|-------|
| Real AI calls in CI | Zero |
| Mechanism | `Prism::fake()` in all AI tests (16 occurrences) |
| Secrets required | None |
| Provider config | `config/ai.php` reads from env, defaults to `openai` |
| QUEUE_CONNECTION | `sync` — acceptable because Prism is faked |
| Queue worker | Not started |

## 12. Concurrency Strategy

```yaml
concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true
```

- `github.ref` for PR events is `refs/pull/N/merge` — each PR has its own group.
- Same PR, new push → cancels previous run.
- Different PRs → separate groups, no cross-cancellation.
- Push to main → its own group.

## 13. Permissions

```yaml
permissions:
  contents: read
```

No write permissions. No PAT. No Azure credential.

## 14. Secrets Required

**None.** Zero GitHub Secrets for KAN-25. MySQL credentials are defined as harmless inline workflow values.

## 15. Job Timeouts

| Job | Timeout | Reasoning |
|-----|---------|-----------|
| `backend-tests` | 15 minutes | MySQL service start (~10s) + composer install (~2min) + migrate + 528 tests (~3min) + Pint. Safety margin for cold cache. |
| `frontend-build` | 10 minutes | npm ci (~1min) + vite build (~2min). Safety margin. |

## 16. Branch Protection Recommendation

After KAN-25 merge, manually configure in GitHub:

```
Branch protection rule for `main`:
  ☑ Require status checks before merging
    - Backend Tests
    - Frontend Build
  ☑ Require branches to be up to date before merging
```

This is a manual GitHub setting, NOT part of the workflow file.

## 17. Verification Strategy

Because feature pushes without a PR do not trigger CI:

1. Implement workflow on `feature/KAN-25-github-actions-ci`.
2. Commit and push the feature branch.
3. Create a PR targeting `main`.
4. Observe real GitHub Actions run in the Actions tab.
5. **Backend Tests** must pass (MySQL service, 528 tests, Pint clean).
6. **Frontend Build** must pass (npm ci, vite build).
7. If a correction is pushed to the open PR branch, CI must rerun (synchronize event).
8. Merge only after green checks.

The **real GitHub Actions execution is mandatory evidence** for completion. CI is not verified before this real run occurs.

## 18. Artifact Strategy

No artifacts. Test results via GitHub Actions UI. Build output has no review value.

## 19. Failure Visibility

```
GitHub PR Checks:
├── Backend Tests (pass/fail)
│   ├── composer validate --strict
│   ├── composer install
│   ├── migrate (MySQL)
│   ├── php artisan test
│   └── vendor/bin/pint --test
└── Frontend Build (pass/fail)
    ├── npm ci
    └── npm run build
```
