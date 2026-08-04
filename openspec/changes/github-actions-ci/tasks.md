# Tasks - KAN-25: Ajouter une intégration continue avec GitHub Actions

## 1. Workflow File Creation

- [x] **T1.1** Create `.github/workflows/` directory.
- [x] **T1.2** Create `.github/workflows/ci.yml` with workflow `name: CI`.
- [x] **T1.3** Configure triggers: `push: branches: [main]` and `pull_request: branches: [main]`.
- [x] **T1.4** Configure concurrency: `group: ${{ github.workflow }}-${{ github.event.pull_request.number || github.ref }}`, `cancel-in-progress: true`.
- [x] **T1.5** Configure permissions: `contents: read`.

## 2. Backend Tests Job

- [x] **T2.1** Define `backend-tests` job on `ubuntu-latest` with display name `Backend Tests`.
- [x] **T2.2** Set job timeout to 15 minutes.
- [x] **T2.3** Add MySQL 8.4 service with health check (`--health-retries=10`), database `cpaflow_test`, user `cpaflow`.
- [x] **T2.4** Add step: `actions/checkout@v4`.
- [x] **T2.5** Add step: `shivammathur/setup-php@v2` with `php-version: '8.4'`, extensions `dom, curl, mbstring, zip, pcntl, pdo, pdo_mysql`, `coverage: none`.
- [x] **T2.6** Add step: `composer validate --strict`.
- [x] **T2.7** Add step: `composer install --prefer-dist --no-interaction --no-progress`.
- [x] **T2.8** Add job-level `env:` block with `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=cpaflow_test`, `DB_USERNAME=cpaflow`, `DB_PASSWORD=cpaflow`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`.
- [x] **T2.9** Add step: `cp .env.example .env` + `php artisan key:generate`.
- [x] **T2.10** Add step: verify MySQL driver via `php artisan tinker --execute="assert(config('database.default') === 'mysql', ...);"`.
- [x] **T2.11** Add step: `php artisan migrate --force`.
- [x] **T2.12** Add step: `php artisan test`.
- [x] **T2.13** Add step: `vendor/bin/pint --test`.

## 3. Frontend Build Job

- [x] **T3.1** Define `frontend-build` job on `ubuntu-latest` with display name `Frontend Build`.
- [x] **T3.2** Set job timeout to 10 minutes.
- [x] **T3.3** Add step: `actions/checkout@v4`.
- [x] **T3.4** Add step: `actions/setup-node@v4` with `node-version: '24'` and `cache: 'npm'`.
- [x] **T3.5** Add step: `npm ci`.
- [x] **T3.6** Add step: `npm run build`.

## 4. Documentation Updates

- [x] **T4.1** Update `README.md` — add CI section: trigger strategy, checks, PHP/Node versions, MySQL service, zero secrets, branch protection recommendation.
- [x] **T4.2** Update `docs/conception-technique.md` — change `CI/CD` from `Pas de pipeline GitHub Actions` to `Implémenté (KAN-25)`. Update deployment architecture diagram. Add MySQL 8.4 and GitHub Actions to Tests table.

## 5. Verification

- [ ] **T5.1** Commit and push workflow to feature branch.
- [ ] **T5.2** Create PR targeting `main`.
- [ ] **T5.3** Verify `backend-tests` job passes (composer validate --strict, MySQL service healthy, 528+ tests PASS, Pint clean).
- [ ] **T5.4** Verify `frontend-build` job passes (npm ci, vite build).
- [ ] **T5.5** Verify both checks appear in PR checks list.
- [ ] **T5.6** Push correction to open PR branch — verify CI reruns (synchronize event).

## 6. Final Review

- [x] **T6.1** Verify no secrets are used or required.
- [x] **T6.2** Verify no production code is modified (only `.github/`, docs/, openspec/).
- [x] **T6.3** Verify no `.env` file is committed.
- [x] **T6.4** Verify workflow file is valid YAML (local inspection).
- [ ] **T6.5** Produce final planning report.

---

**Total implementation checkboxes: 37. Completed: 31. Remaining: 6.**

Remaining tasks require GitHub execution (T5.1–T5.6) or final report production (T6.5).
