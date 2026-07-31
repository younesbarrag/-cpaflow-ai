# Tasks - KAN-14: Generer un lien de tracking pour une campagne

All tasks are unchecked and await implementation approval. Tasks are ordered by dependency.

## 1. Migration

- [x] **T1.1** Create the additive `create_tracking_links_table` migration with the exact columns (`id`, `campaign_id`, `code`), `campaign_id` cascade foreign key, and `code` UNIQUE constraint defined in `design.md`; no `is_active` column; verify by inspecting `php artisan migrate:status` without executing migrations.
- [x] **T1.2** Add migration-focused Pest assertions for cascade behavior, UNIQUE constraint on `code`, and absence of `user_id` and `is_active`; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=tracking_link_schema`.

## 2. Model and Factory

- [x] **T2.1** Create `app/Models/TrackingLink.php` with fillable fields (`campaign_id`, `code`) and `campaign()` BelongsTo relationship; no `is_active` cast or field; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=tracking_link_model`.
- [x] **T2.2** Add `Campaign::trackingLinks()` HasMany relationship; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=campaign_relationship`.
- [x] **T2.3** Create `database/factories/TrackingLinkFactory.php` with valid defaults and Campaign state; no `is_active` state; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=tracking_link_factory`.

## 3. Policy and Authorization

- [x] **T3.1** Add `CampaignPolicy::generateTrackingLink` method deriving ownership through `Campaign → Offer → User` with no Admin bypass; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=tracking_link_policy`.
- [x] **T3.2** Test guest `401`, owner access permitted, foreign existing Campaign `403`, missing Campaign `404`, and foreign inactive Campaign returns `403` before status validation; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=authorization`.

## 4. Domain Exception

- [x] **T4.1** Create `app/Exceptions/CannotGenerateTrackingLink.php` domain exception extending `DomainException` for exhausted collision retries; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=collision_exhaustion`.

## 5. Action

- [x] **T5.1** Create `app/Actions/Campaign/GenerateTrackingLinkAction.php` that receives an authorized active Campaign, generates a unique 32-character code via `Str::random(32)`, creates the TrackingLink through the Campaign relationship, and returns the persisted TrackingLink; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=generates_tracking_link`.
- [x] **T5.2** Collision retry: test that a verified unique-constraint violation on `code` triggers retry, the Action generates a new code, and the INSERT eventually succeeds; test is deterministic by controlling the code sequence or replacing the code generator during the test; do not depend on a real random collision; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=collision_retry`.
- [x] **T5.3** Unrelated exception: test that a database exception that is NOT a unique-constraint violation is rethrown immediately without retry; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=unrelated_db_exception`.
- [x] **T5.4** Collision exhaustion: test that after 5 verified unique-constraint violations the Action throws `CannotGenerateTrackingLink`; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=collision_exhaustion`.

## 6. Form Request

- [x] **T6.1** Create `app/Http/Requests/Api/V1/Campaign/GenerateTrackingLinkRequest.php` with `authorize()` that retrieves the route-bound Campaign and checks `CampaignPolicy::generateTrackingLink`, empty `rules()` (no body parameters), and `after()` hook that validates Campaign status (active → allowed; draft/suspended → `422` with `status` error and message "Only an active campaign can generate tracking links."); verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=form_request`.
- [x] **T6.2** Test that the Form Request authorization order is correct: missing Campaign → `404`, foreign Campaign → `403`, owned draft Campaign → `422`, owned suspended Campaign → `422`; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=form_request_ordering`.

## 7. API Resource

- [x] **T7.1** Create `app/Http/Resources/Api/V1/TrackingLinkResource.php` returning exactly `id`, `campaign_id`, `code`, `url` (generated with `url('/t/' . $code)`), `created_at`, and `updated_at`; no `is_active`, no `user_id`, no Offer or Campaign details; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=tracking_link_resource`.

## 8. Controller

- [x] **T8.1** Add `CampaignController::storeTrackingLink` method that validates via `GenerateTrackingLinkRequest`, optionally re-authorizes with `Gate::authorize('generateTrackingLink', $campaign)` as defense-in-depth, calls `GenerateTrackingLinkAction`, and returns `201` with `TrackingLinkResource` in the `data.tracking_link` envelope; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=creates_tracking_link`.

## 9. Routes

- [x] **T9.1** Register authenticated POST route `POST /api/v1/campaigns/{campaign}/tracking-links` with name `api.v1.campaigns.tracking-links.store` inside the `auth:sanctum` group; no index, show, update, delete, deactivate, rotate, or redirect routes; verify with `php artisan route:list --path=api/v1/campaigns/{campaign}/tracking-links`.

## 10. Pest Feature Tests

- [x] **T10.1** Test owner generates a TrackingLink for an active Campaign → `201`, TrackingLink belongs to the expected Campaign, generated code is exactly 32 characters, code contains URL-safe alphanumeric characters only, code has a database UNIQUE constraint, response follows the `data.tracking_link` envelope, response contains `id`, `campaign_id`, `code`, `url`, `created_at`, `updated_at`; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=creates_tracking_link`.
- [x] **T10.2** Test guest receives `401` and no `tracking_links` row is created; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=authorization`.
- [x] **T10.3** Test foreign Campaign returns `403` and no `tracking_links` row is created; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=authorization`.
- [x] **T10.4** Test foreign inactive Campaign returns `403` before status validation: foreign `draft` Campaign → `403`, foreign `suspended` Campaign → `403`; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=authorization_ordering`.
- [x] **T10.5** Test missing Campaign returns `404`; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=authorization`.
- [x] **T10.6** Test draft Campaign returns `422` with `errors.status` and message "Only an active campaign can generate tracking links." and no `tracking_links` row is created; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=draft_campaign`.
- [x] **T10.7** Test suspended Campaign returns `422` with `errors.status` and message "Only an active campaign can generate tracking links." and no `tracking_links` row is created; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=suspended_campaign`.
- [x] **T10.8** Test rejected generation creates no `tracking_links` row (assert `assertDatabaseCount('tracking_links', 0)` for draft and suspended cases); verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter='draft_campaign|suspended_campaign'`.
- [x] **T10.9** Test repeated generation creates a new independent TrackingLink each time (non-idempotent); verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=repeated_generation`.
- [x] **T10.10** Test codes remain unique across repeated generation; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=code_uniqueness`.
- [x] **T10.11** Test response does not expose `user_id`, `offer_id`, `destination_url`, `is_active`, Campaign name, Campaign budget, Campaign traffic source, or Offer details; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=response_shape`.
- [x] **T10.12** Test generated code is persisted in the database with correct `campaign_id` and non-null `code`; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php --filter=tracking_link_persisted`.

## 11. Documentation

- [x] **T11.1** Update only the final KAN-14 implementation in `docs/conception-technique.md`: MCD, MLD, route, ownership, architecture boundaries, and implemented status; verify with `git diff --check -- docs/conception-technique.md`.
- [x] **T11.2** Ensure documentation does not claim redirect, click, conversion, analytics, attribution, deletion, deactivation, rotation, AI, dashboard, frontend, or Admin functionality; verify with `git diff -- docs/conception-technique.md`.

## 12. Verification

- [x] **T12.1** Run the focused TrackingLink suite: `php artisan test tests/Feature/Api/V1/TrackingLinkApiTest.php`.
- [x] **T12.2** Run the full regression suite: `php artisan test`.
- [x] **T12.3** Run formatting verification without changing files: `vendor/bin/pint --test`.
- [x] **T12.4** Inspect exact route methods, URIs, middleware, and names: `php artisan route:list --path=api/v1/campaigns/{campaign}/tracking-links`.
- [x] **T12.5** Inspect migration registration without executing migrations: `php artisan migrate:status`.
- [x] **T12.6** Check whitespace and patch integrity: `git diff --check`.
- [x] **T12.7** Review the final worktree and confirm only approved KAN-14 implementation/documentation files changed, with no dependency, lockfile, generated, staged, or unrelated changes: `git status --short` and `git diff --stat`.
- [x] **T12.8** Confirm no destructive database command, dependency installation, Jira update, staging, commit, or push occurred; record verification evidence in the implementation report.

## 13. Checkbox Count

**Total implementation checkboxes: 28. Completed: 28. Remaining: 0.**
