# Tasks - KAN-16: Enregistrer une conversion sans doublon

All tasks have been implemented and verified.

## 1. Domain/Schema

- [x] **T1.1** Create the additive `create_conversions_table` migration with the exact columns (`id`, `campaign_id`, `external_id`, `source`, `revenue`, `status`, `converted_at`, `created_at`, `updated_at`), `campaign_id` cascade foreign key, `external_id` UNIQUE constraint, and `status` INDEX; verify by inspecting `php artisan migrate:status` without executing migrations.
- [x] **T1.2** Add migration-focused Pest assertions for cascade behavior (deleting Campaign removes Conversions), UNIQUE constraint on `external_id`, NOT NULL on `external_id`, correct column types, DEFAULT values (`status` = 'pending', no default on `revenue`), nullable `source`, and absence of `tracking_link_id`, `tracking_click_id`, `offer_id`, `user_id`, `payout`, and soft deletes; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=conversion_schema`.

## 2. Model/Relationships

- [x] **T2.1** Create `app/Models/Conversion.php` with fillable fields (`campaign_id`, `external_id`, `source`, `revenue`, `status`, `converted_at`), casts (`revenue` → `'decimal:2'`, `status` → `ConversionStatus::class`, `converted_at` → `'datetime'`), and `campaign()` BelongsTo relationship; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=conversion_model`.
- [x] **T2.2** Add `Campaign::conversions()` HasMany relationship; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=campaign_conversions_relationship`.
- [x] **T2.3** Add `Conversion::campaign()` inverse BelongsTo relationship; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=conversion_inverse_relationship`.
- [x] **T2.4** Create `database/factories/ConversionFactory.php` with valid defaults (Campaign state, unique `external_id`, `source` nullable, revenue from Offer.payout, `status` = pending, `converted_at` = now); verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=conversion_factory`.

## 3. Validation

- [x] **T3.1** Create `app/Http/Requests/Api/V1/Conversion/StoreConversionRequest.php` with `authorize()` that retrieves the route-bound Campaign and checks `CampaignPolicy::recordConversion`, and `rules()` validating `external_id` as `required|string|max:255` (NO `unique` rule) and `source` as `nullable|string|max:255`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=form_request`.
- [x] **T3.2** Confirm that the Form Request rules do NOT include `unique:conversions,external_id` on `external_id` — uniqueness is enforced at the database level only; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=form_request_no_unique_rule`.

## 4. Authorization/Security

- [x] **T4.1** Add `CampaignPolicy::recordConversion(User $user, Campaign $campaign): bool` method to existing `app/Policies/CampaignPolicy.php` using the existing `ownsCampaign` private method; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=campaign_policy`.
- [x] **T4.2** Test guest `401`, owner access permitted, foreign Campaign `403`, missing Campaign `404`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=authorization`.

## 5. Conversion Action

- [x] **T5.1** Create `app/Actions/Conversion/RecordConversionAction.php` with `execute(Campaign $campaign, string $externalId, ?string $source = null): Conversion` that loads `Campaign → Offer`, snapshots `Offer.payout` as revenue, creates the Conversion through `$campaign->conversions()->create()` with `status = ConversionStatus::Pending` and `converted_at = now()`, and catches `UniqueConstraintViolationException` only when the collision is specifically on `external_id` to throw `DuplicateConversionException` — any other unique constraint violation is rethrown; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=records_conversion`.
- [x] **T5.2** Test that `revenue` is snapshotted from `Offer.payout` and not influenced by client input; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=revenue_snapshot`.
- [x] **T5.3** Test that `status` defaults to `pending`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=status_defaults_to_pending`.
- [x] **T5.4** Test that `converted_at` is approximately the current time (server-generated); verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=converted_at_server_generated`.
- [x] **T5.5** Test that `created_at` is set to approximately the current time; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=created_at_timestamp`.
- [x] **T5.6** Test that `source` is persisted when provided; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=source_stored`.
- [x] **T5.7** Test that `source` is null when not provided; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=source_null_when_omitted`.

## 6. Domain Exception

- [x] **T6.1** Create `app/Exceptions/DuplicateConversionException.php` extending `RuntimeException` with `externalId` parameter and message "A conversion with external ID \"{$externalId}\" already exists."; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=duplicate_exception`.
- [x] **T6.2** Register `DuplicateConversionException` in `bootstrap/app.php` with a renderer that returns `409 Conflict` JSON with `message` and `errors.external_id` for API routes, and `null` for non-API routes; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=exception_handler`.

## 7. Duplicate Prevention

- [x] **T7.1** Test that a first valid conversion with `external_id = "TXN-001"` returns `201` and creates exactly one Conversion; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=first_conversion_recorded`.
- [x] **T7.2** Test that a second request with the same `external_id = "TXN-001"` returns `409 Conflict` and does not create a second row; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=duplicate_returns_409`.
- [x] **T7.3** Test that a request with a different `external_id = "TXN-002"` creates a new Conversion and returns `201`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=different_external_id_succeeds`.
- [x] **T7.4** Test that the database UNIQUE constraint prevents duplicates by asserting `assertDatabaseCount('conversions', 1)` after a duplicate attempt; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=database_uniqueness_enforced`.

## 8. Controller/API

- [x] **T8.1** Create `app/Http/Controllers/Api/V1/ConversionController.php` with `store(StoreConversionRequest $request, Campaign $campaign, RecordConversionAction $action): JsonResponse` that calls the Action and returns `201` with `ConversionResource` in the `data.conversion` envelope; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=creates_conversion`.
- [x] **T8.2** Register authenticated POST route `POST /api/v1/campaigns/{campaign}/conversions` with name `api.v1.campaigns.conversions.store` inside the `auth:sanctum` group in `routes/api.php`; verify with `php artisan route:list --path=api/v1/campaigns/{campaign}/conversions`.

## 9. Concurrency Handling

- [x] **T9.1** Test concurrent duplicate requests are safe: dispatch two simultaneous insertions of the same `external_id` using a deterministic method (synchronous sequential inserts within a single test with the database as source of truth); assert exactly one Conversion exists; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=concurrent_duplicate_safe`.

## 10. Resources/Responses

- [x] **T10.1** Create `app/Http/Resources/Api/V1/ConversionResource.php` returning exactly `id`, `campaign_id`, `external_id`, `source`, `revenue` (two-decimal string), `status` (enum value), `converted_at` (ISO 8601), `created_at` (ISO 8601), `updated_at` (ISO 8601); no `tracking_link_id`, `tracking_click_id`, `offer_id`, `user_id`, or `payout`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=conversion_resource`.
- [x] **T10.2** Test that the response does not expose `tracking_link_id`, `tracking_click_id`, `offer_id`, `user_id`, or `payout`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=response_shape`.

## 11. Relationships

- [x] **T11.1** Test `Campaign::conversions()` returns the correct Conversions; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=campaign_conversions_relationship`.
- [x] **T11.2** Test `Conversion::campaign()` returns the correct Campaign; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=conversion_inverse_relationship`.
- [x] **T11.3** Test cascade deletion: deleting a Campaign removes its Conversions; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=cascade_behavior`.

## 12. Pest Feature Tests

- [x] **T12.1** Test valid conversion records exactly one Conversion with correct attributes; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=records_conversion`.
- [x] **T12.2** Test response has `201` status and correct `data.conversion` envelope; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=creates_conversion`.
- [x] **T12.3** Test unknown Campaign returns `404`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=unknown_campaign`.
- [x] **T12.4** Test missing `external_id` returns `422` with `errors.external_id`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=missing_external_id`.
- [x] **T12.5** Test empty `external_id` returns `422`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=empty_external_id`.
- [x] **T12.6** Test duplicate `external_id` returns `409`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=duplicate_returns_409`.
- [x] **T12.7** Test foreign Campaign returns `403`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=foreign_campaign`.
- [x] **T12.8** Test guest returns `401`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=guest_returns_401`.
- [x] **T12.9** Test `revenue` is snapshotted from Offer; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=revenue_snapshot`.
- [x] **T12.10** Test `status` defaults to `pending`; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=status_defaults_to_pending`.
- [x] **T12.11** Test `converted_at` is server-generated; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=converted_at_server_generated`.
- [x] **T12.12** Test `source` is accepted when provided; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=source_stored`.
- [x] **T12.13** Test `source` is null when omitted; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=source_null_when_omitted`.
- [x] **T12.14** Test `Campaign::conversions()` works; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=campaign_conversions_relationship`.
- [x] **T12.15** Test `Conversion::campaign()` works; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=conversion_inverse_relationship`.
- [x] **T12.16** Test cascade deletion works; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php --filter=cascade_behavior`.
- [x] **T12.17** Test KAN-14 generation behavior remains unaffected; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkGenerationApiTest.php`.
- [x] **T12.18** Test KAN-15 redirect behavior remains unaffected; verify with `php artisan test tests/Feature/TrackingRedirectTest.php`.

## 13. Postman/Newman

- [x] **T13.1** Create `postman/CPAFlow-AI-KAN-16.postman_collection.json` (Collection v2.1) with tests for: health check, register owner, login, create offer, create campaign, record conversion (201), record duplicate conversion (409), record different conversion (201), unknown campaign (404); verify with Newman run.
- [x] **T13.2** Verify Postman collection passes against local environment; do not force database state through debug endpoints — persistence behavior remains covered by Pest.

## 14. Documentation

- [x] **T14.1** Update only the final KAN-16 implementation in `docs/conception-technique.md`: update MLD for `conversions` table (confirm `campaign_id`, `external_id` NOT NULL, `source` nullable, `revenue` NOT NULL, `status`, `converted_at`), add implementation status, add route to API routes list; verify with `git diff --check -- docs/conception-technique.md`.
- [x] **T14.2** Ensure documentation does not claim dashboard, analytics, status transitions, attribution, AI, frontend, postback secret, batch import, tracking link attribution, or admin functionality; verify with `git diff -- docs/conception-technique.md`.

## 15. Regression/Formatting

- [x] **T15.1** Run formatting verification without changing files: `vendor/bin/pint --test`.
- [x] **T15.2** Run the focused Conversion suite: `php artisan test tests/Feature/Api/V1/ConversionApiTest.php`.
- [x] **T15.3** Run the full regression suite: `php artisan test`.
- [x] **T15.4** Inspect exact route methods, URIs, middleware, and names: `php artisan route:list --path=api/v1/campaigns/{campaign}/conversions`.
- [x] **T15.5** Inspect migration registration without executing: `php artisan migrate:status`.
- [x] **T15.6** Check whitespace and patch integrity: `git diff --check`.

## 16. Final Review

- [x] **T16.1** Review the final worktree and confirm only approved KAN-16 implementation/documentation files changed, with no dependency, lockfile, generated, staged, or unrelated changes: `git status --short` and `git diff --stat`.
- [x] **T16.2** Confirm no destructive database command, dependency installation, Jira update, staging, commit, or push occurred; record verification evidence in the implementation report.

## 17. Checkbox Count

**Total implementation checkboxes: 49. Completed: 49. Remaining: 0.**
