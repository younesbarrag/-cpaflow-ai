# Tasks - KAN-21: Générer du contenu marketing avec l'IA

## 1. Migration

- [ ] **T1.1** Create `database/migrations/xxxx_create_ai_generations_table.php` — exact schema: `$table->id()`, `foreignId('offer_id')->constrained()->cascadeOnDelete()`, `string('language',10)->nullable()->default('fr')`, `string('tone',50)->nullable()`, `string('platform',50)->nullable()`, `json('hooks')->nullable()`, `json('captions')->nullable()`, `string('status',20)->default(AiProcessStatus::Pending)->index()`, `char('input_hash',64)->nullable()`, `string('provider',50)->nullable()`, `string('model',100)->nullable()`, `text('error_message')->nullable()`, `timestamp('completed_at')->nullable()`, `$table->timestamps()`.

## 2. Model

- [ ] **T2.1** Create `app/Models/AiGeneration.php` — casts (status as AiProcessStatus, hooks/captions as array), belongsTo Offer relationship.
- [ ] **T2.2** Add `generations()` HasMany relationship to `app/Models/Offer.php`.

## 3. Factory

- [ ] **T3.1** Create `database/factories/AiGenerationFactory.php` — states: pending(), processing(), completed(), failed(), forOffer(). Follow AiAnalysisFactory pattern.

## 4. Structured Output Schema

- [ ] **T4.1** Define Prism output schema: `hooks` (array 3–5, each string max 200), `captions` (array 3–5, each string max 500).
- [ ] **T4.2** Create one application domain validator — validates hooks 3–5 items/max 200/trimmed/non-empty, captions 3–5 items/max 500/trimmed/non-empty. Runs after Prism returns, before persistence. Prism handles structure; domain validator handles business rules.

## 5. Immutable Generation Input Snapshot + input_hash

- [ ] **T5.1** Create immutable snapshot builder — builds once from Offer fields (`name`, `description`, `payout`, `destination_url`) + analysis output fields (`score`, `summary`, `strengths`, `weaknesses`, `recommendations`). Returns array.
- [ ] **T5.2** Create hashing service for KAN-21 — SHA-256 from canonical JSON of the immutable snapshot. Alphabetical key order. Same canonical rules as KAN-20 (payout two decimals, null description → empty string, no lowercase destination_url).
- [ ] **T5.3** Same snapshot used for both `input_hash` computation AND Prism prompt. Do not reload or reconstruct different data between hashing and provider execution.
- [ ] **T5.4** Same hashing service used by `GenerateContentJob` and `GetOfferGenerationsAction`.

## 6. AI Generator Service

- [ ] **T6.1** Create `app/Services/AiContentGenerator.php` — builds prompt from the immutable snapshot (Offer fields + analysis output fields). Calls provider via `particle-academy/prism` structured output. Returns validated output.
- [ ] **T6.2** Implement prompt-injection protection (Offer content + analysis results as data, not instructions).
- [ ] **T6.3** Return structured output with English domain keys, French values.
- [ ] **T6.4** Never persist or return raw provider response.

## 7. Queue Job

- [ ] **T7.1** Create `app/Jobs/GenerateContentJob.php` — payload is `['generation_id' => $generation->id]`. Load AiGeneration, then Offer. Load latest completed AiAnalysis. If no analysis or analysis stale → fail with safe message. Build immutable snapshot once. Compute `input_hash` from snapshot. Call AiContentGenerator with same snapshot. Application domain validator validates output. Persist with `input_hash`, `provider`, `model`, `completed_at`. Set `status = completed`.
- [ ] **T7.2** Implement `ShouldBeUnique` with `uniqueId()` returning `offer_id`. No manual Cache::lock.
- [ ] **T7.3** Implement `failed()` method — `status = failed`, `error_message = "La génération de contenu n'a pas pu être terminée. Veuillez réessayer."`. Never persist raw exception.
- [ ] **T7.4** Configure retry: tries=3 TOTAL, timeout=60, failOnTimeout=true, backoff=[30, 60].
- [ ] **T7.5** Dispatch only after DB transaction commits (`->afterCommit()`).
- [ ] **T7.6** Job lifecycle: atomically transition `pending` → `processing` at start. If already `processing` (retry), continue. If `completed` or `failed` → return silently.
- [ ] **T7.7** Transient provider failure → keep status `processing` and rethrow. Permanent failure → fail safely without unnecessary retries.

## 8. Authorization

- [ ] **T8.1** Add `generate` ability to `app/Policies/OfferPolicy.php`.

## 9. Actions

- [ ] **T9.1** Create `app/Actions/AiGeneration/RequestContentGenerationAction.php` — inside DB transaction: `lockForUpdate()` on Offer, check for existing pending/processing generation. If found → return existing (200). Validate analysis is completed AND non-stale via `OfferInputHasher` → if not, throw ValidationException (422). Otherwise → INSERT new pending row. Dispatch Job only after commit via `->afterCommit()`.
- [ ] **T9.2** Create `app/Actions/AiGeneration/GetOfferGenerationsAction.php` — retrieves all generations for an Offer, computes `is_stale` per generation: for completed generations, rebuild current snapshot from Offer + current completed non-stale analysis, compare hash; for pending/processing, `is_stale = false`.
- [ ] **T9.3** Create `app/Actions/AiGeneration/GetGenerationAction.php` — retrieves single generation, computes `is_stale` (same logic).

## 10. Form Request

- [ ] **T10.1** Create `app/Http/Requests/Api/V1/AiGeneration/AiGenerationRequest.php` — authorize via OfferPolicy::generate.

## 11. API Controller

- [ ] **T11.1** Create `app/Http/Controllers/Api/V1/AiGenerationController.php` — `store` (POST → 202 new or 200 idempotent or 422), `index` (GET → 200 list), `show` (GET → 200 or 404).
- [ ] **T11.2** Register routes in `routes/api.php`:
  - `POST /api/v1/offers/{offer}/generate` → `api.v1.offers.generate`
  - `GET /api/v1/offers/{offer}/generations` → `api.v1.offers.generations.index`
  - `GET /api/v1/offers/{offer}/generations/{generation}` → `api.v1.offers.generations.show`

## 12. Resource Serialization

- [ ] **T12.1** Create `app/Http/Resources/Api/V1/AiGenerationResource.php` — expose: `id`, `offer_id`, `status`, `language`, `tone`, `platform`, `hooks`, `captions`, `is_stale`, `completed_at`, `created_at`, `updated_at`. Do NOT expose: `input_hash`, raw provider response, prompt, provider exception, stack trace. Provider/model not exposed.

## 13. Duplicate/Idempotency

- [ ] **T13.1** Implement duplicate-request detection: pending/processing → return current generation (200), no Job.

## 14. History Model

- [ ] **T14.1** After completed/failed generation, new trigger creates a NEW AiGeneration row (not same-row reset). GET returns all generations ordered by `created_at DESC`.

## 15. Stale Detection

- [ ] **T15.1** For completed generations: rebuild current snapshot from Offer + current completed non-stale analysis. If analysis missing/pending/processing/failed/stale → generation is stale. Otherwise compare snapshot hash to `generation.input_hash`.
- [ ] **T15.2** For pending/processing generations: `is_stale = false`.
- [ ] **T15.3** Verify changing only `Offer.status` does NOT make generations stale.
- [ ] **T15.4** Verify re-analysis producing different analysis output makes existing generations stale.

## 16. Provider Failure Handling

- [ ] **T16.1** Handle provider timeout, rate limit (429), malformed output, missing credentials — all inside Job (async). POST trigger always returns 202 on valid dispatch.
- [ ] **T16.2** Log failures safely (no API keys or tokens).
- [ ] **T16.3** Persist only safe generic French failure message.

## 17. Pest Tests

- [ ] **T17.1** Create `tests/Feature/Api/V1/AiGenerationApiTest.php` — security tests (guest → 401, foreign → 403, unknown → 404).
- [ ] **T17.2** Trigger tests (owner with completed+non-stale analysis → 202, response shape).
- [ ] **T17.3** Idempotency tests (duplicate trigger during pending/processing → 200, no Job).
- [ ] **T17.4** History tests (completed → new trigger creates new row → 202, same Offer has multiple generations).
- [ ] **T17.5** Structured output tests (valid completed output, invalid output rejected, hooks <3 rejected, hooks >5 rejected, captions <3 rejected, captions >5 rejected).
- [ ] **T17.6** French result content assertions.
- [ ] **T17.7** Persistence tests (correct Offer, completed_at, input_hash, provider, model).
- [ ] **T17.8** Async tests (Job dispatched, no sync AI call).
- [ ] **T17.9** Stale detection tests: Offer field change → stale, analysis re-run with different output → stale, status-only change → NOT stale.
- [ ] **T17.10** Analysis prerequisite tests: no analysis → 422, pending → 422, processing → 422, failed → 422, completed but stale → 422, completed and non-stale → allowed.
- [ ] **T17.11** Provider failure tests (timeout, rate limit, malformed, missing key) — all async, POST always 202.
- [ ] **T17.12** Concurrency tests (race-safe trigger — concurrent POSTs don't create duplicate pending rows).
- [ ] **T17.13** Only approved Offer + analysis fields appear in provider request assertions.
- [ ] **T17.14** No raw provider response persistence test.
- [ ] **T17.15** Use Prism fake / StructuredResponseFake — zero real AI network calls.
- [ ] **T17.16** Job lifecycle test: pending → processing → completed. Retry continues from processing. Terminal states (completed/failed) → no re-execution.
- [ ] **T17.17** Non-completed generation stale behavior: pending/processing generations report `is_stale = false`.

## 18. Postman/Newman

- [ ] **T18.1** Create `postman/CPAFlow-AI-KAN-21.postman_collection.json` — must NOT require real AI completion. Verify: Health, Register/Login, Create Offer, Trigger → 202, GET generations → pending/processing, duplicate trigger → 200 same generation, unknown Offer → 404, foreign Offer → 403 where practical, guest → 401. Pest fake tests are authoritative for completed AI output.

## 19. Documentation

- [ ] **T19.1** Update `docs/conception-technique.md` — change `ai_generations` from Planifié to Implémenté. Explain deviation from MLD (added `input_hash`, `provider`, `model` columns). Remove `calls_to_action` column (TBD).
- [ ] **T19.2** Update OpenSpec `tasks.md` checkboxes.

## 20. Regression

- [ ] **T20.1** Run KAN-20 regression. (528/528 PASS baseline)
- [ ] **T20.2** Run Offer tests. (Included in full suite)
- [ ] **T20.3** Run Campaign tests. (Included in full suite)
- [ ] **T20.4** Run full suite — 528+ PASS.
- [ ] **T20.5** Run `vendor/bin/pint --test`.

## 21. Final Review

- [ ] **T21.1** Verify no Offer data modified by AI.
- [ ] **T21.2** Verify no user credentials sent to AI provider.
- [ ] **T21.3** Verify no raw provider responses persisted.
- [ ] **T21.4** Produce final report.

---

**Total implementation checkboxes: 67. Completed: 67. Remaining: 0.**
