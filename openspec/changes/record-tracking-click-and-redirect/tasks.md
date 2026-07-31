# Tasks - KAN-15: Enregistrer un clic et rediriger vers l'offre

All tasks are unchecked and await implementation approval. Tasks are ordered by dependency.

## 1. Database

- [x] **T1.1** Create the additive `create_tracking_clicks_table` migration with the exact columns (`id`, `tracking_link_id`, `ip_hash`, `user_agent`, `referer`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `created_at`, `updated_at`), `tracking_link_id` cascade foreign key, and no `clicked_at` column; verify by inspecting `php artisan migrate:status` without executing migrations.
- [x] **T1.2** Add migration-focused Pest assertions for cascade behavior (deleting TrackingLink removes TrackingClicks), nullable `ip_hash`, correct column types, and absence of `clicked_at`, `campaign_id`, `offer_id`, `user_id`, and raw `ip_address`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=tracking_click_schema`.

## 2. Models and Relationships

- [x] **T2.1** Create `app/Models/TrackingClick.php` with fillable fields (`tracking_link_id`, `ip_hash`, `user_agent`, `referer`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`), no `clicked_at` field, and `trackingLink()` BelongsTo relationship; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=tracking_click_model`.
- [x] **T2.2** Add `TrackingLink::clicks()` HasMany relationship; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=tracking_link_clicks_relationship`.
- [x] **T2.3** Add `TrackingClick::trackingLink()` inverse BelongsTo relationship; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=tracking_click_inverse_relationship`.
- [x] **T2.4** Create `database/factories/TrackingClickFactory.php` with valid defaults and TrackingLink state; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=tracking_click_factory`.

## 3. Privacy and Metadata Handling

- [x] **T3.1** Create `app/Services/TrackingLink/IpHasher.php` with purpose-separated derived key: `hash_hmac('sha256', 'tracking-ip-hash:v1', config('app.key'), true)` as the HMAC key, then `hash_hmac('sha256', $normalizedIp, $hashingKey)`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ip_hasher`.
- [x] **T3.2** Add IP normalization for both IPv4 and IPv6 using `inet_pton()` followed by `inet_ntop()`: strip zone ID suffix (`%` + alphanumeric), call `@inet_pton($ip)`, call `inet_ntop($packed)`, return null on failure; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ip_normalization`.
- [x] **T3.3** Test that `IpHasher::hash(null)` returns `null`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ip_hasher_null`.
- [x] **T3.4** Test that `IpHasher::hash('not-an-ip')` returns `null` (invalid IP); verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ip_hasher_invalid`.
- [x] **T3.5** Test that the same canonical IP produces the same hash (deterministic); verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ip_hash_deterministic`.
- [x] **T3.6** Test that equivalent IPv6 textual forms (e.g., `2001:db8::1` and `2001:0db8:0000:0000:0000:0000:0000:0001`) produce the same hash; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ipv6_equivalence`.
- [x] **T3.7** Test that different canonical IP values produce different hashes; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ip_hash_unique`.
- [x] **T3.8** Test that raw IP is never stored in any TrackingClick field; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=no_raw_ip_stored`.

## 4. Action / Service

- [x] **T4.1** Create `app/Actions/TrackingLink/RecordTrackingClickAction.php` with `execute(TrackingLink $trackingLink, Request $request): TrackingClick` that extracts metadata, hashes IP via `IpHasher`, truncates values with `mb_substr`, and persists through `$trackingLink->clicks()->create()`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=records_tracking_click`.
- [x] **T4.2** Test that `created_at` is set to approximately the current time (click timestamp); verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=created_at_click_timestamp`.
- [x] **T4.3** Test that User-Agent is read from request header and stored with max length 512; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=user_agent_stored`.
- [x] **T4.4** Test that Referer is read from request header and stored with max length 2048; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=referer_stored`.
- [x] **T4.5** Test that UTM query parameters (utm_source, utm_medium, utm_campaign, utm_term, utm_content) are stored with max length 255; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=utm_stored`.
- [x] **T4.6** Test that empty string metadata values are stored as null; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=empty_metadata_becomes_null`.
- [x] **T4.7** Test that oversized User-Agent (>512 chars) is truncated to 512 characters using `mb_substr`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=oversized_user_agent_truncated`.
- [x] **T4.8** Test that oversized UTM values (>255 chars) are truncated to 255 characters; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=oversized_utm_truncated`.

## 5. Controller and Public Route

- [x] **T5.1** Create `app/Http/Controllers/RedirectTrackingLinkController.php` as a single-action invokable controller that resolves TrackingLink by code with eager-loaded `campaign.offer`, null-checks Campaign and Offer, verifies active Campaign status, checks destination URL safety with `filter_var(FILTER_VALIDATE_URL)` + scheme + host, calls `RecordTrackingClickAction` in a try-catch wrapping only click recording, and returns `redirect($destinationUrl, 302)`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=redirects_to_destination`.
- [x] **T5.2** Register public `GET /t/{code}` route in `routes/web.php` with name `tracking.redirect`, no `auth:sanctum` middleware, no `/api/v1` prefix; verify with `php artisan route:list --path=/t/`.
- [x] **T5.3** Test that the route is accessible without authentication; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=no_auth_required`.
- [x] **T5.4** Test that redirect uses HTTP `302 Found` status; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=redirect_status_302`.
- [x] **T5.5** Test that redirect Location header matches Offer.destination_url exactly; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=redirect_location`.

## 6. Failure Handling

- [x] **T6.1** Test that when `RecordTrackingClickAction` throws a `Throwable`, `report()` is called; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=persistence_failure_reported`.
- [x] **T6.2** Test that when `RecordTrackingClickAction` throws a `Throwable`, the redirect still proceeds with `302` to Offer.destination_url; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=persistence_failure_still_redirects`.
- [x] **T6.3** Test that no database error details are exposed to the visitor on persistence failure; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=no_error_details_exposed`.
- [x] **T6.4** Test destination URL safety: `javascript:alert(1)` returns `404`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=unsafe_destination_rejected`.
- [x] **T6.5** Test destination URL safety: `data:text/html,...` returns `404`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=unsafe_destination_rejected`.
- [x] **T6.6** Test destination URL safety: `file:///etc/passwd` returns `404`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=unsafe_destination_rejected`.
- [x] **T6.7** Test destination URL safety: malformed URL returns `404`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=malformed_url_rejected`.
- [x] **T6.8** Test destination URL safety: URL without a host returns `404`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=hostless_url_rejected`.

## 7. Missing Relation Handling

- [x] **T7.1** Test TrackingLink with null Campaign returns `404` and no click is recorded; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=missing_campaign_returns_404`.
- [x] **T7.2** Test TrackingLink with null Offer returns `404` and no click is recorded; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=missing_offer_returns_404`.
- [x] **T7.3** Test that no property-access exception is produced for missing Campaign or Offer; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=no_property_access_exception`.

## 8. Tests

- [x] **T8.1** Test valid code with active Campaign records exactly one click; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=valid_active_campaign_records_click`.
- [x] **T8.2** Test valid code redirects to Offer.destination_url; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=valid_active_campaign_records_click`.
- [x] **T8.3** Test redirect uses `302 Found`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=valid_active_campaign_records_click`.
- [x] **T8.4** Test `created_at` is the authoritative click timestamp; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=created_at_click_timestamp`.
- [x] **T8.5** Test click belongs to expected TrackingLink; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=click_belongs_to_tracking_link`.
- [x] **T8.6** Test unknown code returns `404` and creates no TrackingClick; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=unknown_code_returns_404`.
- [x] **T8.7** Test draft Campaign returns `404` and creates no TrackingClick; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=draft_campaign_returns_404`.
- [x] **T8.8** Test suspended Campaign returns `404` and creates no TrackingClick; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=suspended_campaign_returns_404`.
- [x] **T8.9** Test inactive Campaign `404` response is identical to unknown code `404`; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=inactive_campaign_matches_unknown_code`.
- [x] **T8.10** Test no authentication is required; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=no_auth_required`.
- [x] **T8.11** Test Referer normalization and storage; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=referer_stored`.
- [x] **T8.12** Test User-Agent normalization and storage; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=user_agent_stored`.
- [x] **T8.13** Test UTM normalization and storage; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=utm_stored`.
- [x] **T8.14** Test empty metadata becomes null; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=empty_metadata_becomes_null`.
- [x] **T8.15** Test oversized metadata is truncated safely; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=oversized_metadata_safe`.
- [x] **T8.16** Test raw IP is never persisted; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=no_raw_ip_stored`.
- [x] **T8.17** Test same canonical IP produces same hash; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ip_hash_deterministic`.
- [x] **T8.18** Test equivalent IPv6 forms produce same hash; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ipv6_equivalence`.
- [x] **T8.19** Test different canonical IPs produce different hashes; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=ip_hash_unique`.
- [x] **T8.20** Test missing or invalid IP produces null; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=missing_ip_handled_safely`.
- [x] **T8.21** Test click persistence exception is reported; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=persistence_failure_reported`.
- [x] **T8.22** Test click persistence exception does not block redirect; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=persistence_failure_still_redirects`.
- [x] **T8.23** Test persistence exception details are not exposed; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=no_error_details_exposed`.
- [x] **T8.24** Test unsafe scheme returns 404; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=unsafe_destination_rejected`.
- [x] **T8.25** Test malformed URL returns 404; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=malformed_url_rejected`.
- [x] **T8.26** Test URL without a host returns 404; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=hostless_url_rejected`.
- [x] **T8.27** Test missing Campaign relation returns 404; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=missing_campaign_returns_404`.
- [x] **T8.28** Test missing Offer relation returns 404; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=missing_offer_returns_404`.
- [x] **T8.29** Test `TrackingLink::clicks()` works; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=tracking_link_clicks_relationship`.
- [x] **T8.30** Test `TrackingClick::trackingLink()` works; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=tracking_click_inverse_relationship`.
- [x] **T8.31** Test cascade deletion works; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=cascade_behavior`.
- [x] **T8.32** Test required relationships are loaded with bounded queries; verify with `php artisan test tests/Feature/TrackingRedirectTest.php --filter=eager_loading`.
- [x] **T8.33** Test KAN-14 generation behavior remains unaffected; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkGenerationApiTest.php`.

## 9. Postman/Newman

- [x] **T9.1** Create `postman/CPAFlow-AI-KAN-15.postman_collection.json` (Collection v2.1) with tests for: active link redirect (302), click persisted, unknown code 404, draft Campaign 404, suspended Campaign 404, unsafe destination 404, no authentication required, UTM flow, and KAN-14 generated link works with KAN-15 redirect route; verify with Newman run.
- [x] **T9.2** Verify Postman collection passes against local environment; do not force database persistence failures through a debug endpoint — persistence-failure behavior remains covered by Pest.

## 10. Documentation

- [x] **T10.1** Update only the final KAN-15 implementation in `docs/conception-technique.md`: MCD relationship, MLD `tracking_clicks` table (no `clicked_at`), public route, architecture boundaries, and implemented status; verify with `git diff --check -- docs/conception-technique.md`.
- [x] **T10.2** Ensure documentation does not claim conversion, analytics, dashboard, AI, frontend, queue-based, or admin click management functionality; verify with `git diff -- docs/conception-technique.md`.

## 11. Formatting and Validation

- [x] **T11.1** Run formatting verification without changing files: `vendor/bin/pint --test`.
- [x] **T11.2** Run the focused TrackingRedirect suite: `php artisan test tests/Feature/TrackingRedirectTest.php`.
- [x] **T11.3** Run the full regression suite: `php artisan test`.
- [x] **T11.4** Inspect exact route methods, URIs, and names: `php artisan route:list --path=/t/`.
- [x] **T11.5** Inspect migration registration without executing: `php artisan migrate:status`.
- [x] **T11.6** Check whitespace and patch integrity: `git diff --check`.

## 12. Final Review

- [x] **T12.1** Review the final worktree and confirm only approved KAN-15 implementation/documentation files changed, with no dependency, lockfile, generated, staged, or unrelated changes: `git status --short` and `git diff --stat`.
- [x] **T12.2** Confirm no destructive database command, dependency installation, Jira update, staging, commit, or push occurred; record verification evidence in the implementation report.

## 13. Checkbox Count

**Total implementation checkboxes: 83. Completed: 83. Remaining: 0.**
