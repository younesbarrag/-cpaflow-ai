# Proposal — KAN-23: Final QA, Test Stability & Demo Data

## Problem Statement

After 15 completed stories (KAN-8 through KAN-22, KAN-25, KAN-31), the application has:

- **1 confirmed flaky test** that fails ~67% of the time due to random factory state
- **No deterministic demo data** — the database is empty after migration
- **No admin demo account** — KAN-22 deliberately excluded this
- **An incomplete conversion workflow** — conversions are created as `Pending` but never approved/rejected, making dashboard revenue permanently zero via normal HTTP flow
- **No end-to-end Postman verification** covering the full business flow

## Goal

Make the existing application:
1. Deterministic — no flaky tests, stable factory defaults
2. Demo-ready — seeded with realistic, verifiable data
3. Evaluation-ready — E2E flow demonstrable from clean environment
4. Conversion gap documented and classified for follow-up

## Scope

### In Scope

| Area | Work |
|------|------|
| Flaky test fix | Fix `CampaignWebTest::success flash renders after campaign_creation` |
| Factory audit | Fix `OfferFactory` random status default, add missing states |
| Timing test hardening | Widen 1s tolerance to 5s in `ConversionApiTest` |
| Demo seeder | Create `DemoDataSeeder` with deterministic data, production-safe, idempotent |
| Demo admin account | Admin + affiliate accounts for KAN-22 demo |
| DemoDataSeeder test | Pest coverage for seeder correctness and idempotency |
| Conversion approval | NOT implementing — classify gap as release blocker |
| Final Postman collection | Unified E2E collection covering full business flow |
| Documentation | Update `docs/conception-technique.md` with demo setup |

### Explicitly Out of Scope

| Area | Reason |
|------|--------|
| Conversion approval/rejection feature | Separate story — project release blocker, not KAN-23 implementation |
| FK index migration | MySQL already auto-creates indexes on FK columns — verified, no migration needed |
| Blade UI redesign | KAN-31 Phase 1 is sufficient for now |
| Login rate limiting | Security improvement, separate concern |
| Tracking redirect rate limiting | Security improvement, separate concern |
| User deletion cascade fix | Requires architectural decision, separate story |
| Offer name uniqueness | Business decision needed, not QA |
| Production seeder changes | Demo data is local-only |
| CI modifications | KAN-25 is stable and verified |
| New business features | KAN-23 is QA/stability only |

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Fix factory default, not test | Tests should create explicit domain state; factories should default to valid neutral |
| Demo seeder as separate class | `DemoDataSeeder` called explicitly, not in `DatabaseSeeder` |
| Demo seeder refuses production | `app()->environment('production')` guard with exception |
| Demo credentials are non-production | `admin@example.test` / `affiliate@example.test` / `affiliate2@example.test` — obvious test accounts |
| No conversion approval implementation | Project release blocker — requires separate story with design decisions |
| No FK index migration | MySQL InnoDB already creates indexes on FK columns — verified via `SHOW INDEX` |
| Final Postman collection replaces per-KAN collections | One authoritative E2E collection |
| AI demo hashes use production services | `OfferInputHasher` + `GenerationInputHasher` ensure non-stale records |

## Success Criteria

1. `php artisan test` passes 600/600 consistently (triple-run verified)
2. `vendor/bin/pint --test` passes
3. `composer validate --strict` passes
4. `npm run build` passes
5. `php artisan db:seed --class=DemoDataSeeder` creates deterministic demo data
6. Second run of seeder produces identical record counts
7. Newman final collection passes all assertions
8. Demo admin can demonstrate KAN-22 user management
9. Flaky test is eliminated
10. No migration executed
