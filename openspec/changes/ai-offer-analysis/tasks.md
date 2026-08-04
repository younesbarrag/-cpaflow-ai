# Tasks - KAN-20: Analyser une offre avec l'IA

## 1. AI Provider Installation

- [x] **T1.1** Install `particle-academy/prism` via `composer require particle-academy/prism`. **Do not proceed with implementation tasks until this is complete.**

## 2. AI Configuration

- [x] **T2.1** Add AI configuration variables to `.env.example`: `AI_PROVIDER`, `AI_MODEL`, plus provider-specific credentials (e.g. `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`). No generic `AI_API_KEY`.
- [x] **T2.2** Create `config/ai.php` with provider/model configuration.
- [x] **T2.3** Verify missing-key behavior fails safely (no raw exception to client — generic French message).

## 3. Migration

- [x] **T3.1** Create `database/migrations/xxxx_create_ai_analyses_table.php` — exact schema: `$table->id()`, `foreignId('offer_id')->constrained()->cascadeOnDelete()`, `unique('offer_id')`, `string('status',20)->default(AiProcessStatus::Pending)->index()`, `unsignedTinyInteger('score')->nullable()`, `text('summary')->nullable()`, `json('strengths')->nullable()`, `json('weaknesses')->nullable()`, `json('recommendations')->nullable()`, `char('input_hash',64)->nullable()`, `string('provider',50)->nullable()`, `string('model',100)->nullable()`, `text('error_message')->nullable()`, `timestamp('completed_at')->nullable()`, `$table->timestamps()`.

## 4. Model

- [x] **T4.1** Create `app/Models/AiAnalysis.php` — casts, belongsTo Offer relationship, scope.
- [x] **T4.2** Add `analysis()` relationship to `app/Models/Offer.php`.

## 5. Structured Output Schema

- [x] **T5.1** Define output schema: `score` (integer 0–100, required when completed), `summary` (string, max 1000), `strengths` (array 0–5, each string max 200), `weaknesses` (same), `recommendations` (same).
- [x] **T5.2** Create output validation logic — validate provider response twice: in Job + before persistence.

## 6. input_hash

- [x] **T6.1** Create hashing service — SHA-256 from canonical JSON of `name`, `description`, `payout`, `destination_url`. No `mb_strtolower()`. No NFKC normalization. No ext-intl dependency.
- [x] **T6.2** Stable key order (alphabetical). Canonicalize payout to two decimals. Normalize nullable description consistently. Do NOT lowercase destination_url.
- [x] **T6.3** Same hashing service used by `AnalyzeOfferJob` and `GetOfferAnalysisAction`.

## 7. AI Analyzer Service

- [x] **T7.1** Create `app/Services/AiOfferAnalyzer.php` — builds prompt from Offer fields (name, description, payout, destination_url only), calls provider via `particle-academy/prism` structured output, returns validated output.
- [x] **T7.2** Implement prompt-injection protection (Offer content as data, not instructions).
- [x] **T7.3** Return structured output with English domain keys, French values.
- [x] **T7.4** Never persist or return raw provider response.

## 8. Queue Job

- [x] **T8.1** Create `app/Jobs/AnalyzeOfferJob.php` — payload is `['analysis_id' => $analysis->id]`. Load AiAnalysis, then Offer. Build immutable AI-input snapshot. Compute `input_hash` from that snapshot. Call AiOfferAnalyzer. Application validates output. Persist with `input_hash`, `provider`, `model`, `completed_at`. Set `status = completed`.
- [x] **T8.2** Implement `ShouldBeUnique` with `uniqueId()` returning `offer_id`. No manual Cache::lock.
- [x] **T8.3** Implement `failed()` method — `status = failed`, `error_message = "L'analyse IA n'a pas pu être terminée. Veuillez réessayer."`. Never persist raw exception.
- [x] **T8.4** Configure retry: tries=3 TOTAL, timeout=60, failOnTimeout=true, backoff=[30, 60].
- [x] **T8.5** Dispatch only after DB transaction commits (`->afterCommit()`).
- [x] **T8.6** Transient provider failure → throw/retry. Permanent failure → fail safely without unnecessary retries.

## 9. Authorization

- [x] **T9.1** Add `analyze` ability to `app/Policies/OfferPolicy.php`.

## 10. Actions

- [x] **T10.1** Create `app/Actions/AiAnalysis/RequestOfferAnalysisAction.php` — inside DB transaction: `lockForUpdate()` on Offer, inspect existing AiAnalysis. pending/processing → return existing (200). none → create pending row. completed/failed → reset SAME row to pending (clear summary, strengths, weaknesses, recommendations, score, error_message, input_hash, completed_at, provider, model). Dispatch Job only after commit via `->afterCommit()`.
- [x] **T10.2** Create `app/Actions/AiAnalysis/GetOfferAnalysisAction.php` — retrieves current analysis, computes `is_stale` via `input_hash` comparison (NOT `Offer.updated_at`).

## 11. Form Request

- [x] **T11.1** Create `app/Http/Requests/Api/V1/AiAnalysis/AiAnalysisRequest.php` — authorize via OfferPolicy::analyze.

## 12. API Controller

- [x] **T12.1** Create `app/Http/Controllers/Api/V1/AiAnalysisController.php` — `analyze` (POST → 200 idempotent or 202 new/reset) and `show` (GET → 200 or 404).
- [x] **T12.2** Register routes in `routes/api.php`.

## 13. Resource Serialization

- [x] **T13.1** Create `app/Http/Resources/Api/V1/AiAnalysisResource.php` — expose: `id`, `offer_id`, `status`, `score`, `summary`, `strengths`, `weaknesses`, `recommendations`, `is_stale`, `completed_at`, `created_at`, `updated_at`. Do NOT expose: `input_hash`, raw provider response, prompt, provider exception, stack trace. Provider/model not exposed.

## 14. Duplicate/Idempotency

- [x] **T14.1** Implement duplicate-request detection: pending/processing → return current analysis (200), no Job.

## 15. Re-Analysis

- [x] **T15.1** Implement re-analysis: completed/failed → UPDATE same row to pending, dispatch Job (202). No delete/recreate. Same analysis ID preserved.

## 16. Stale Detection

- [x] **T16.1** Implement stale detection via `input_hash` comparison: `offer.input_hash !== analysis.input_hash`.
- [x] **T16.2** Verify changing only `Offer.status` does NOT make analysis stale.

## 17. Provider Failure Handling

- [x] **T17.1** Handle provider timeout, rate limit (429), malformed output, missing credentials.
- [x] **T17.2** Log failures safely (no API keys or tokens).
- [x] **T17.3** Persist only safe generic French failure message.

## 18. Pest Tests

- [x] **T18.1** Create `tests/Feature/Api/V1/AiAnalysisApiTest.php` — security tests (guest → 401, foreign → 403, unknown → 404).
- [x] **T18.2** Trigger tests (owner → 202, response shape).
- [x] **T18.3** Idempotency tests (duplicate trigger during pending/processing → 200, no Job).
- [x] **T18.4** Re-analysis tests (completed/failed → same row reset to pending → 202, Job dispatched, same analysis ID).
- [x] **T18.5** Structured output tests (valid completed output, invalid output rejected, score <0 rejected, score >100 rejected).
- [x] **T18.6** French result content assertions.
- [x] **T18.7** Persistence tests (correct Offer, completed_at, input_hash, provider, model).
- [x] **T18.8** Async tests (Job dispatched, no sync AI call).
- [x] **T18.9** Stale detection tests: name change → stale, description change → stale, payout change → stale, destination_url change → stale, status-only change → NOT stale.
- [x] **T18.10** Provider failure tests (timeout, rate limit, malformed, missing key).
- [x] **T18.11** Concurrency tests (race-safe trigger — concurrent POSTs don't create duplicate rows).
- [x] **T18.12** UNIQUE offer_id constraint test.
- [x] **T18.13** Only approved Offer fields appear in provider request assertions.
- [x] **T18.14** No raw provider response persistence test.
- [x] **T18.15** Use Prism fake / StructuredResponseFake — zero real AI network calls.

## 19. Postman/Newman

- [x] **T19.1** Create `postman/CPAFlow-AI-KAN-20.postman_collection.json` — must NOT require real AI completion. Verify: Health, Register/Login, Create Offer, Trigger → 202, GET analysis → pending/processing, duplicate trigger → 200 same analysis, unknown Offer → 404, foreign Offer → 403 where practical, guest → 401. Pest fake tests are authoritative for completed AI output.

## 20. Documentation

- [x] **T20.1** Update `docs/conception-technique.md` — change `ai_analyses` from Planifié to Implémenté. Explain deviation from `resultats JSON` to explicit columns.
- [x] **T20.2** Update OpenSpec `tasks.md` checkboxes.

## 21. Regression

- [x] **T21.1** Run KAN-19 regression. (528/528 PASS — KAN-19 tests included)
- [x] **T21.2** Run Offer tests. (Included in 528/528)
- [x] **T21.3** Run Campaign tests. (Included in 528/528)
- [x] **T21.4** Run full suite — 490+ PASS. (528/528 PASS)
- [x] **T21.5** Run `vendor/bin/pint --test`. (PASS)

## 22. Final Review

- [x] **T22.1** Verify no Offer data modified by AI.
- [x] **T22.2** Verify no user credentials sent to AI provider.
- [x] **T22.3** Verify no raw provider responses persisted.
- [x] **T22.4** Produce final report.

---

**Total implementation checkboxes: 63. Completed: 63. Remaining: 0.**
