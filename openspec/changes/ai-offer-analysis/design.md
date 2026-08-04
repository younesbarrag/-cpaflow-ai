# Design - KAN-20: Analyser une offre avec l'IA

## 1. AI Provider

**Approved decision:** Use `particle-academy/prism` as the unified AI provider abstraction.

```bash
composer require particle-academy/prism
```

The `Prism\Prism` namespace remains unchanged.

Prism provides:
- Unified API for OpenAI, Anthropic, Mistral, Ollama, and more.
- Structured output support via `Prism::structured()`.
- Provider-specific rate-limit handling.
- First-class Laravel support.

**Configuration:**

```
AI_PROVIDER=openai
AI_MODEL=gpt-4o-mini
OPENAI_API_KEY=sk-...
```

Provider/model are config-driven. Provider-specific credentials (e.g. `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`) are defined per provider. No real key may be committed.

Missing configuration must result in a safe failed analysis with a generic client-facing message.

**Prism must be installed before any code that depends on it.** Tasks that touch `AiAnalyzer`, `AnalyzeOfferJob`, Pest tests, and Postman Newman must be ordered after installation.

## 2. Structured Output Schema

### 2a. AI Input (Offer fields sent to provider)

Only four Offer fields are sent to the AI provider. **No URL is fetched.** The destination_url is included as raw text for context only:

| Field | Type | Usage |
|-------|------|-------|
| `name` | string | Offer name |
| `description` | string | Offer description |
| `payout` | numeric | Offer payout value |
| `destination_url` | string | Offer landing URL (raw text, NOT fetched) |

**No other Offer fields are sent** (no status, no id, no user data, no financial internals).

### 2b. AI Output (structured, French values, English domain keys)

Domain/API field names are English. Generated values are French.

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `score` | integer | yes | Required when completed; integer; 0–100 inclusive |
| `summary` | string | yes | Required; max 1000 chars |
| `strengths` | array | yes | 0–5 items; each string max 200 chars, trimmed, non-empty |
| `weaknesses` | array | yes | 0–5 items; each string max 200 chars, trimmed, non-empty |
| `recommendations` | array | yes | 0–5 items; each string max 200 chars, trimmed, non-empty |

**score semantics:**
- 0–30: faible (low attractiveness)
- 31–70: moyen (average attractiveness)
- 71–100: élevé (high attractiveness)

Database result fields remain nullable while pending/processing/failed.

### 2c. Raw Provider Response

**Never persist the raw provider response.** Not even in a nullable column. Not even when `APP_DEBUG=true`.

- On success: extract validated fields → persist → discard raw response.
- On failure: persist only a safe generic message → discard raw exception/response.
- The raw response must not be written to the application log with user/API-key context.

## 3. Deterministic input_hash (staleness detection)

A SHA-256 `input_hash` is computed from a canonical JSON representation of exactly four Offer fields. **No `mb_strtolower()`. No NFKC normalization. No ext-intl dependency.**

```
input_hash = sha256(canonical_json({
  "name": offer.name,
  "description": normalized(offer.description),
  "payout": formatted(offer.payout),
  "destination_url": offer.destination_url
}))
```

- **Key order** is stable: `name`, `description`, `destination_url`, `payout` (alphabetical).
- **payout** is canonicalized to two decimal places (project's established representation).
- **description** is normalized consistently (null → empty string or consistent nullable handling).
- **destination_url** is NOT lowercased (URL paths may be case-sensitive).
- The same hashing service is used by `AnalyzeOfferJob` and `GetOfferAnalysisAction`.
- The Job computes the hash from the exact Offer snapshot used to construct the provider prompt.
- If the Offer changes after the AI request starts, the completed analysis naturally becomes stale because the current Offer hash differs.
- Changing `Offer.status` only does NOT change the hash.

## 4. Re-Analysis Behavior

Re-analysis **reuses the same `ai_analyses` row**. Do not delete and recreate.

**State transitions:**

```
(no row)          → INSERT pending row
pending           → return current, no Job
processing        → return current, no Job
completed         → UPDATE same row → pending, dispatch Job
failed            → UPDATE same row → pending, dispatch Job
```

**Implementation:** `updateOrCreate` on `(offer_id)` with `lockForUpdate()` inside a DB transaction. The UPDATE resets the row to `pending` and nullifies `summary`, `strengths`, `weaknesses`, `recommendations`, `score`, `error_message`, `input_hash`, `completed_at`, `provider`, `model` before the Job is dispatched.

**Job dispatch after commit:** The Job is dispatched only after the DB transaction commits to prevent the Job from seeing a row that hasn't been committed yet.

## 5. API Contract

### POST /api/v1/offers/{offer}/analyze

| Scenario | HTTP | Body | Job? |
|----------|------|------|------|
| No analysis row exists | 202 | new pending analysis | yes |
| Row is `pending` or `processing` | 200 | same analysis (idempotent) | no |
| Row is `completed` or `failed` | 202 | same row reset to `pending` | yes |

### GET /api/v1/offers/{offer}/analysis

| Scenario | HTTP |
|----------|------|
| Completed analysis exists | 200 with analysis (includes `is_stale`) |
| No analysis row | 404 |

**Response JSON uses English domain keys:** `score`, `summary`, `strengths`, `weaknesses`, `recommendations`. The values generated by the AI are in French.

## 6. Status Lifecycle

```
pending → processing → completed
                   ↘ failed
completed → pending  (re-analysis)
failed    → pending  (re-analysis)
```

- `pending`: row created, Job queued or about to be dispatched.
- `processing`: Job has started execution.
- `completed`: AI output validated and persisted.
- `failed`: unrecoverable error (missing key, invalid output, provider error after retries).

The Job's `failed()` method is the **only** place that may set `status = failed`. Never set `failed` on the first transient attempt — let retries happen.

## 7. Queue Job (AnalyzeOfferJob)

| Property | Value |
|----------|-------|
| Queue | default (database) |
| Tries | 3 TOTAL attempts |
| Timeout | 60s |
| failOnTimeout | true |
| Backoff | [30, 60] (excludes final attempt) |
| Unique | `ShouldBeUnique` (no manual Cache::lock) |

**Job payload:** `['analysis_id' => $analysis->id]` (not `offer_id`).

**Job execution flow:**
1. Load `AiAnalysis` by ID.
2. If not found or status ≠ `processing` → return silently.
3. Load related Offer.
4. Build one immutable AI-input snapshot from: `name`, `description`, `payout`, `destination_url`.
5. Compute `input_hash` from that exact snapshot.
6. Build AI prompt from the same snapshot.
7. Call Prism structured output.
8. Application validates returned structured result before persistence.
9. Persist validated fields + `input_hash` + `provider` + `model` + `completed_at = now()`.
10. Set `status = completed`.

**failed() method:**
- Log safe message (no raw exception details).
- Set `status = failed`, `error_message = "L'analyse IA n'a pas pu être terminée. Veuillez réessayer."`.
- Do NOT persist raw provider response.

**Retry semantics:**
- **Transient provider failure:** throw/retry; do NOT permanently mark failed on the first transient attempt.
- **After final exhaustion:** `failed()` marks the analysis failed.
- **Permanent configuration/output failure:** fail safely without unnecessary retries.

## 8. Database Schema

### `ai_analyses` table

```php
$table->id();

$table->foreignId('offer_id')
    ->constrained()
    ->cascadeOnDelete()
    ->unique();

$table->string('status', 20)
    ->default(AiProcessStatus::Pending->value)
    ->index();

$table->unsignedTinyInteger('score')->nullable();

$table->text('summary')->nullable();

$table->json('strengths')->nullable();
$table->json('weaknesses')->nullable();
$table->json('recommendations')->nullable();

$table->char('input_hash', 64)->nullable();

$table->string('provider', 50)->nullable();
$table->string('model', 100)->nullable();

$table->text('error_message')->nullable();

$table->timestamp('completed_at')->nullable();

$table->timestamps();
```

**Deliberate deviation from the older MLD `resultats JSON` design:**
KAN-20 has a stable structured domain contract. Therefore `summary`, `strengths`, `weaknesses`, `recommendations` are represented explicitly as dedicated columns instead of storing one opaque provider result blob. This provides typed queries, explicit nullability, and individual field indexing.

**Never store a raw provider response.**

## 9. Security

- **Prompt injection protection:** Offer content is injected into a system prompt that explicitly treats it as untrusted data. No instructions can be embedded in Offer fields.
- **No user credentials sent:** Only name, description, payout, destination_url sent to AI.
- **No raw provider responses:** Not persisted, not logged with user context, not returned to client.
- **Safe error messages:** Only generic French messages returned (`"L'analyse IA n'a pas pu être terminée. Veuillez réessayer."`).
- **Rate limiting:** Existing `throttle:api` middleware.
- **Authorization:** `OfferPolicy::analyze` ability (owner-only).

## 10. KAN-20/KAN-21 Boundary

| KAN-20 | KAN-21 |
|--------|--------|
| Offer analysis | Marketing content generation |
| Score, summary, strengths, weaknesses, recommendations | Hooks, captions, calls-to-action |
| Single analysis per Offer | Multiple content types per Offer |
| Read-only output | Generative output |
| English domain keys, French values | TBD |
