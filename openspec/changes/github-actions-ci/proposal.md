# Proposal - KAN-25: Ajouter une intégration continue avec GitHub Actions

## 1. Summary

Add a GitHub Actions CI pipeline that automatically validates every Pull Request before merge. The pipeline runs two independent jobs: `backend-tests` (composer validate, Pest tests against MySQL 8.4, Laravel Pint) and `frontend-build` (npm ci, Vite build). CI uses an ephemeral MySQL 8.4 service container for database fidelity, PHP 8.4, Node 24, requires zero secrets, and provides clear failure signals per check type.

## 2. Problem

Without CI, code quality gates depend on developers remembering to run tests, Pint, and build locally before merging. This creates risk: forgotten test failures, formatting drift, and broken frontend builds can reach the main branch. KAN-25 establishes the safety net that makes branch protection possible for KAN-21 and all later stories.

## 3. Objectives

- Run `php artisan test` (Pest) against MySQL 8.4 on every PR to validate the 528/528 test baseline.
- Run `vendor/bin/pint --test` to enforce code style.
- Run `composer validate --strict` to enforce composer.json integrity.
- Run `npm ci && npm run build` to validate Vite frontend assets.
- Fail fast with clear job names so GitHub reports exactly which check failed.
- Require zero project secrets — no AI keys, no database credentials, no deployment tokens.
- Cancel superseded runs on the same PR.

## 4. In Scope

- `.github/workflows/ci.yml` — single workflow file with two jobs.
- `backend-tests` job: PHP 8.4, MySQL 8.4 service, composer validate, composer install, .env setup, php artisan key:generate, php artisan migrate --force, php artisan test, vendor/bin/pint --test.
- `frontend-build` job: Node 24, npm ci, npm run build.
- Concurrency cancellation for superseded PR pushes.
- Minimal permissions (contents: read).
- Documentation update (README.md, conception-technique.md).

## 5. Out of Scope

- Azure deployment (KAN-26).
- Docker deployment (KAN-32).
- Production secrets or credentials.
- Release publishing or semantic-release.
- Code coverage thresholds.
- Dependabot configuration.
- Security scanning expansion.
- Branch protection rule configuration (recommended but not automated).
- Automated PR merge or branch deletion.
- Real AI provider calls (Prism::fake handles all tests).

## 6. Success Criteria

- Opening a PR targeting `main` triggers CI automatically.
- Pushing to an open PR branch triggers CI (synchronize event).
- `backend-tests` job passes: composer validate --strict OK, 528/528 Pest tests PASS against MySQL 8.4, Pint clean.
- `frontend-build` job passes: npm ci succeeds, vite build succeeds.
- Both jobs appear as required checks in PR to main.
- Zero GitHub Secrets configured.
- CI run completes in under 10 minutes for backend, under 3 minutes for frontend.
- Cancelled superseded runs show "Cancelled" status in GitHub Actions UI.
