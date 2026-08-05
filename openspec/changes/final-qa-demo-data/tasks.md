# Tasks — KAN-23: Final QA, Test Stability & Demo Data

## 1. Flaky Test Fix

- [x] **T1.1** Fix `tests/Feature/Web/CampaignWebTest.php` line 246: change `Offer::factory()->for($this->user)->create()` to `Offer::factory()->for($this->user)->draft()->create()`. Verify by running the test 10 times consecutively.

## 2. Factory Defaults

- [x] **T2.1** Fix `database/factories/OfferFactory.php`: change `status` default from `fake()->randomElement(OfferStatus::cases())` to `OfferStatus::Draft`.
- [x] **T2.2** Add `suspended()` state to `OfferFactory`: returns `['status' => OfferStatus::Suspended]`.
- [x] **T2.3** Add `archived()` state to `OfferFactory`: returns `['status' => OfferStatus::Archived]`.
- [x] **T2.4** Fix `database/factories/UserFactory.php`: add `'role' => UserRole::Affiliate` to `definition()`.
- [x] **T2.5** Fix `database/factories/ConversionFactory.php`: change eager `Campaign::factory()->create(['status' => CampaignStatus::Active])` to lazy `Campaign::factory()->active()` in `definition()`.
- [x] **T2.6** Fix `database/factories/CampaignExpenseFactory.php`: change eager `Campaign::factory()->create(['status' => CampaignStatus::Active])` to lazy `Campaign::factory()->active()` in `definition()`.

## 3. Timing Test Hardening

- [x] **T3.1** Fix `tests/Feature/Api/V1/ConversionApiTest.php`: widen timing tolerance from `subSecond()`/`addSecond()` to `subSeconds(5)`/`addSeconds(5)` in the `generates converted_at as approximately the current time` test.

## 4. Demo Seeder

- [x] **T4.1** Create `database/seeders/DemoDataSeeder.php` with production guard: `app()->environment('production')` → throw `RuntimeException`.
- [x] **T4.2** Seed demo Admin: `admin@example.test` / `password` / role `admin` via `updateOrCreate`.
- [x] **T4.3** Seed demo Affiliate: `affiliate@example.test` / `password` / role `affiliate` via `updateOrCreate`.
- [x] **T4.4** Seed demo Affiliate2: `affiliate2@example.test` / `password` / role `affiliate` via `updateOrCreate`.
- [x] **T4.5** Seed 3 Offers for affiliate using deterministic lookup with `forceCreate`: "DEMO — Fitness Offer" (active, $25.00), "DEMO — Draft Offer" (draft, $10.00), "DEMO — Archived Offer" (archived, $15.00).
- [x] **T4.6** Seed 2 Campaigns using `DB::table()->updateOrInsert()`: "DEMO — Active Campaign" (active, on active offer), "DEMO — Draft Campaign" (draft, on draft offer).
- [x] **T4.7** Seed 1 TrackingLink on active campaign with deterministic fixed code (`demo1234567890demo1234567890de`).
- [x] **T4.8** Seed 3 TrackingClicks using `DB::table()->updateOrInsert()` with deterministic UTM markers: `demo-click-1` (today), `demo-click-2` (today startOfDay), `demo-click-3` (15 days ago).
- [x] **T4.9** Seed 3 Conversions using `DB::table()->updateOrInsert()` with deterministic `external_id`: 2 approved ($25.00 each, today and 15 days ago), 1 pending ($25.00, 15 days ago).
- [x] **T4.10** Seed 2 CampaignExpenses using `DB::table()->updateOrInsert()` with deterministic descriptions: $40.00 (15 days ago), $30.00 (today).
- [x] **T4.11** Seed 1 AiAnalysis (completed, score 85) on active offer. Compute `input_hash` using `OfferAiInputSnapshot::fromOffer()` + `OfferInputHasher::compute()`.
- [x] **T4.12** Seed 1 AiGeneration (completed, hooks + captions) on active offer. Compute `input_hash` using `OfferContentGenerationSnapshot::fromOfferAndAnalysis()` + `GenerationInputHasher::compute()`.
- [x] **T4.13** Verify demo financial consistency: revenue $50.00, expenses $70.00, profit -$20.00 via `GetDashboardStatisticsAction`.

## 5. DemoDataSeeder Test

- [x] **T5.1** Create `tests/Feature/Database/DemoDataSeederTest.php`.
- [x] **T5.2** Test: seeder creates admin demo account with correct role.
- [x] **T5.3** Test: seeder creates affiliate demo account with correct role.
- [x] **T5.4** Test: seeder creates affiliate2 demo account with correct role.
- [x] **T5.5** Test: seeder creates correct demo offers (count = 3 for demo affiliate).
- [x] **T5.6** Test: seeder creates correct demo campaigns (count = 2).
- [x] **T5.7** Test: seeder creates correct demo conversions (count = 3).
- [x] **T5.8** Test: seeder creates correct demo expenses (count = 2).
- [x] **T5.9** Test: seeded AI analysis is completed and non-stale (input_hash matches production hasher).
- [x] **T5.10** Test: seeded AI generation is completed and non-stale (input_hash matches production hasher).
- [x] **T5.11** Test: seeder is idempotent — run twice → identical record counts.
- [x] **T5.12** Test: seeder idempotent — run twice → identical dashboard totals via `GetDashboardStatisticsAction`.
- [x] **T5.13** Test: seeder refuses production environment.
- [x] **T5.14** Test: no provider/network/queue calls during seeding.

## 6. Documentation

- [x] **T6.1** Update `docs/conception-technique.md` — add "Données de démo" section with setup instructions (`php artisan db:seed --class=DemoDataSeeder`), demo accounts, DEMO ONLY warning, expected dashboard totals, and conversion approval gap documentation.
- [x] **T6.2** Update `openspec/changes/final-qa-demo-data/README.md` with status, files, quick start, key decisions.

## 7. Postman/Newman

- [x] **T7.1** Create `postman/CPAFlow-AI-KAN-23.postman_collection.json` — unified E2E collection covering: health, register (unique QA email per run), login, offers CRUD, campaigns CRUD, tracking link, conversion, expenses, dashboard, AI analysis (existing demo), AI generation (existing demo), admin login, admin list users, admin show user, affiliate forbidden, guest forbidden.
- [x] **T7.2** Verify Newman collection passes all assertions against local server after `php artisan db:seed --class=DemoDataSeeder`.

## 8. Regression

- [x] **T8.1** Run full test suite 3 times consecutively — 615/615 PASS each time (600 original + 15 new DemoDataSeederTest).
- [x] **T8.2** Run `vendor/bin/pint --test` — PASS.
- [x] **T8.3** Run `composer validate --strict` — PASS.
- [x] **T8.4** Run `npm run build` — PASS.
- [x] **T8.5** Run `php artisan route:list` — 31 API routes, 71 total, no duplicates, all expected.
- [x] **T8.6** Run `php artisan db:seed --class=DemoDataSeeder` — verify expected graph.
- [x] **T8.7** Run `php artisan db:seed --class=DemoDataSeeder` again — verify identical totals.

---

**Total implementation checkboxes: 46. Completed: 46. Remaining: 0.**
