# Tasks - KAN-13: Creer et gerer les campagnes CPA

All tasks are complete and verified. Tasks were ordered by dependency and implementation followed approval.

## 1. Migration

- [x] **T1.1** Create the additive `create_campaigns_table` migration with the exact columns, defaults, `offer_id` cascade foreign key, and indexes defined in `design.md`; verify by inspecting `php artisan migrate:status` without executing migrations.
- [x] **T1.2** Add migration-focused Pest assertions for exact defaults, decimal persistence, Offer cascade behavior, and absence of a duplicated `user_id`; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_schema`.

## 2. Enum

- [x] **T2.1** Reuse `app/Enums/CampaignStatus.php` (created KAN-8) containing only `draft`, `active`, and `suspended`; unchanged during KAN-13; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_status`.
- [x] **T2.2** Encode and test the transition matrix `draft -> active`, `active -> suspended`, and `suspended -> active`, with every other/repeated operation rejected; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=transition`.

## 3. Model and Factory

- [x] **T3.1** Create `app/Models/Campaign.php` with fillable business fields (name, traffic_source, budget, status; offer_id excluded), `budget` decimal cast, status enum cast, and `offer()` relationship; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_model`.
- [x] **T3.2** Add `Offer::campaigns()` relationship without adding direct Campaign ownership; authenticated-user listing is scoped through `whereHas('offer', user_id)` without a `User::campaigns()` has-many-through; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_relationships`.
- [x] **T3.3** Create `database/factories/CampaignFactory.php` with valid defaults and Offer/status states; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_factory`.

## 4. Policy and Authorization

- [x] **T4.1** Create `CampaignPolicy` methods `view`, `update`, `activate`, and `suspend`, deriving ownership through Campaign Offer User and adding no Admin bypass; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_policy`.
- [x] **T4.2** Add `OfferPolicy::createCampaign` for strict parent ownership and preserve Laravel auto-discovery; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=offer_authorization`.
- [x] **T4.3** Test guest `401`, owner access, foreign existing resource `403`, missing resource `404`, and authorization-before-validation precedence; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=authorization`.

## 5. Form Requests

- [x] **T5.1** Create `StoreCampaignRequest` with normalization, exact field rules, prohibited `status`, cached global Offer resolution, ownership authorization before archived-offer validation, and archived-owned-Offer `422`; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=store_validation`.
- [x] **T5.2** Create `UpdateCampaignRequest` with policy authorization, true PATCH rules, empty-editable-payload rejection, and prohibited `offer_id`, `user_id`, and `status`; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=update_validation`.
- [x] **T5.3** Confirm no Index Form Request, search, or filter is added and page-only pagination remains valid; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_listing`.

## 6. Actions and Domain Error

- [x] **T6.1** Create `CreateCampaignAction` accepting an authorized Offer and validated domain inputs but no status input, creating through the Offer relationship and explicitly setting `CampaignStatus::Draft`; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=creates_campaign`.
- [x] **T6.2** Create `UpdateCampaignAction` with an internal editable-field whitelist and immutable ownership/status; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=partially_updates`.
- [x] **T6.3** Create `ActivateCampaignAction` enforcing draft/suspended to active and containing no HTTP or authorization logic; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=activat`.
- [x] **T6.4** Create `SuspendCampaignAction` enforcing active to suspended and containing no HTTP or authorization logic; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=suspend`.
- [x] **T6.5** Create the campaign transition domain exception and map only it to a stable `409` JSON response in Laravel exception configuration; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=invalid_transition`.

## 7. API Resource

- [x] **T7.1** Create `CampaignResource` with id, parent Offer id/name, name, traffic source, two-decimal budget string, enum value, and Offer-consistent dates; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_resource`.
- [x] **T7.2** Ensure list/show/mutation queries explicitly eager-load only Offer `id` and `name`, and test listing with lazy loading prevented or relation-loaded assertions so CampaignResource cannot cause N+1 queries; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=offer_context`.

## 8. Controller

- [x] **T8.1** Create thin `CampaignController::store` and `index` methods: Store resolves Offer globally, returns `404` if missing, authorizes `OfferPolicy::createCampaign` with foreign `403`, rejects an owned archived Offer with `422`, then calls Create Action; index uses a user-scoped query, eager-loads `offer:id,name`, orders `id DESC`, and paginates 15; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter='creates_campaign|campaign_listing'`.
- [x] **T8.2** Add thin `show` and `update` methods with policy authorization, validated inputs, Update Action, and CampaignResource; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter='views_campaign|partially_updates'`.
- [x] **T8.3** Add thin `activate` and `suspend` methods with policy authorization and lifecycle Actions; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter='activat|suspend'`.

## 9. Routes

- [x] **T9.1** Register authenticated GET index, POST store, GET show, and PATCH update routes with exact `api.v1.campaigns.*` names; verify with `php artisan route:list --path=api/v1/campaigns`.
- [x] **T9.2** Register authenticated POST activate and suspend routes and add no delete/archive/tracking/conversion routes; verify with `php artisan route:list --path=api/v1/campaigns`.

## 10. Pest Feature Tests

- [x] **T10.1** Test owner creation and persisted Offer link, normalized fields, two-decimal budget, and draft status; test guest creation `401` and no row persisted; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=creates_campaign`.
- [x] **T10.2** Test missing Offer `404`, foreign Offer `403` including invalid payload precedence, and archived owned Offer `422`, asserting no Campaign is persisted; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=offer`.
- [x] **T10.3** Test invalid/empty/oversized name, invalid traffic source, negative/oversized budget, excessive decimal precision, and any submitted Store status including `draft`, `active`, `suspended`, or an unknown value, asserting `422` and database absence; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=validation`.
- [x] **T10.4** Test owner listing includes only Campaigns connected to their Offers, spans multiple Offers, excludes foreign Campaigns, orders by descending id, returns empty data when appropriate, and serializes Offer id/name without relying on lazy loading; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=campaign_listing`.
- [x] **T10.5** Test page size 15 plus exact `data`, `links`, and `meta.current_page`, `last_page`, `per_page`, and `total`; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=pagination`.
- [x] **T10.6** Test owner show `200` and resource fields, foreign show `403`, missing show `404`, and guest show `401`; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=views_campaign`.
- [x] **T10.7** Test owner partial name/traffic-source/budget updates and normalization, asserting submitted fields persist and omitted fields remain unchanged; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=partially_updates`.
- [x] **T10.8** Test empty PATCH and unknown-only PATCH return `422`; each of `offer_id`, `user_id`, and `status` returns `422` both alone and alongside valid editable fields; assert ownership, status, and all data remain unchanged; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter='empty_patch|protected'`.
- [x] **T10.9** Test owner draft activation, active suspension, and suspended reactivation, asserting persisted status and unchanged non-status fields; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter='activat|suspend'`.
- [x] **T10.10** Test draft-to-suspend, repeated activate on active, and repeated suspend on suspended each return `409`; separately assert no database write by preserving status, non-status fields, and `updated_at`; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter='invalid_transition|repeated'`.
- [x] **T10.11** Test guests cannot activate/suspend (`401`) and foreign users cannot activate/suspend (`403`), asserting persisted status remains unchanged; verify with `php artisan test tests/Feature/Api/V1/CampaignApiTest.php --filter=lifecycle_authorization`.

## 11. Documentation

- [x] **T11.1** Update only the final KAN-13 implementation in `docs/conception-technique.md`: MCD, MLD, enum/transitions, routes, ownership, pagination, architecture boundaries, and implemented status; verify with `git diff --check -- docs/conception-technique.md`.
- [x] **T11.2** Ensure documentation does not claim tracking, click, conversion, analytics, attribution, deletion, archival, AI, dashboard, frontend, or Admin functionality; verify with `git diff -- docs/conception-technique.md`.

## 12. Verification

- [x] **T12.1** Run the focused Campaign suite: `php artisan test tests/Feature/Api/V1/CampaignApiTest.php`.
- [x] **T12.2** Run the full regression suite: `php artisan test`.
- [x] **T12.3** Run formatting verification without changing files: `vendor/bin/pint --test`.
- [x] **T12.4** Inspect exact route methods, URIs, middleware, and names: `php artisan route:list --path=api/v1/campaigns`.
- [x] **T12.5** Inspect migration registration without executing migrations: `php artisan migrate:status`.
- [x] **T12.6** Check whitespace and patch integrity: `git diff --check`.
- [x] **T12.7** Review the final worktree and confirm only approved KAN-13 implementation/documentation files changed, with no dependency, lockfile, generated, staged, or unrelated changes: `git status --short` and `git diff --stat`.
- [x] **T12.8** Confirm no destructive database command, dependency installation, Jira update, staging, commit, or push occurred; record verification evidence in the implementation report.

## 13. Checkbox Count

**Total implementation checkboxes: 46. Completed: 46. Remaining: 0.**
