# Design - KAN-21: Générer du contenu marketing avec l'IA

## 1. AI Provider

**Reuses KAN-20 infrastructure.** `particle-academy/prism` is already installed.

```
AI_PROVIDER=openai
AI_MODEL=gpt-4o-mini
```

Provider/model are config-driven via `config/ai.php`. No new configuration needed.

## 2. KAN-20/KAN-21 Boundary

| KAN-20 | KAN-21 |
|--------|--------|
| Offer analysis | Marketing content generation |
| Score, summary, strengths, weaknesses, recommendations | Hooks, captions |
| Single analysis per Offer (`UNIQUE(offer_id)`) | Multiple generations per Offer (history model) |
| Read-only output | Generative output |
| English domain keys, French values | English domain keys, French values |
| input_hash: Offer fields only (name, description, payout, destination_url) | input_hash: Offer fields + analysis output fields (score, summary, strengths, weaknesses, recommendations) |
| Re-analysis reuses same row, stable ID | Each generation trigger creates new row |

## 3. History Model (Multiple Generations)

KAN-21 uses a **history model**: each trigger creates a NEW `ai_generations` row. This allows users to regenerate content when Offer data changes, when analysis is re-run, or when they want fresh copy. The `GET /generations` endpoint returns all generations ordered by `created_at DESC`.

**Key difference from KAN-20:** KAN-20 reuses the same row (UNIQUE on `offer_id`). KAN-21 creates new rows (no UNIQUE constraint on `offer_id`).

**Idempotency:** Only prevents duplicate triggers when a generation is `pending` or `processing`. After `completed`/`failed`, a new trigger creates a new row.

## 4. Analysis Prerequisite

Generation requires a **completed AND current (non-stale)** `AiAnalysis`. The trigger endpoint validates:

| Analysis state | HTTP | Reason |
|----------------|------|--------|
| No analysis row | 422 | No analysis exists |
| `pending` | 422 | Analysis not yet completed |
| `processing` | 422 | Analysis not yet completed |
| `failed` | 422 | Analysis did not complete |
| `completed` but stale relative to current Offer | 422 | Analysis is outdated |
| `completed` and current (non-stale) | allowed | Prerequisite met |

Stale detection for the analysis reuses KAN-20's `OfferInputHasher`: compare `AiAnalysis.input_hash` against the current Offer's hash. If they differ, the analysis is stale.

**Safe French message:** `"Une analyse IA terminée et à jour est requise avant de générer du contenu."`

## 5. Structured Output Schema

### 5a. AI Input (Offer + Analysis fields sent to provider)

| Field | Type | Usage |
|-------|------|-------|
| `name` | string | Offer name |
| `description` | string | Offer description |
| `payout` | numeric | Offer payout value |
| `destination_url` | string | Offer landing URL (raw text, NOT fetched) |
| `analysis_score` | integer | KAN-20 analysis score (0–100) |
| `analysis_summary` | string | KAN-20 analysis summary |
| `analysis_strengths` | array | KAN-20 analysis strengths |
| `analysis_weaknesses` | array | KAN-20 analysis weaknesses |
| `analysis_recommendations` | array | KAN-20 analysis recommendations |

**No other Offer fields are sent** (no status, no id, no user data, no financial internals).

### 5b. AI Output (structured, French values, English domain keys)

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `hooks` | array | yes | 3–5 items; each string max 200 chars, trimmed, non-empty |
| `captions` | array | yes | 3–5 items; each string max 500 chars, trimmed, non-empty |

**hooks semantics:** Short, attention-grabbing headlines for ad campaigns. French language. Platform-agnostic (platform is metadata only).

**captions semantics:** Longer-form social media post text. French language. Includes emoji-style formatting suggestions. Platform-agnostic.

Database result fields remain nullable while pending/processing/failed.

### 5c. Raw Provider Response

**Never persist the raw provider response.** Same rule as KAN-20.

## 6. Immutable Generation Input Snapshot

The **immutable generation input snapshot** is the single source of truth for both:
1. `input_hash` computation (staleness detection)
2. Prism prompt/provider request

This snapshot must be built **once** and used identically for hashing and prompting. Do not reload or reconstruct different data between hashing and provider execution.

**Snapshot contents:**

```php
$snapshot = [
    'name'                     => $offer->name,
    'description'              => $offer->description ?? '',
    'payout'                   => number_format($offer->payout, 2, '.', ''),
    'destination_url'          => $offer->destination_url,
    'analysis_score'           => $analysis->score,
    'analysis_summary'         => $analysis->summary,
    'analysis_strengths'       => $analysis->strengths,
    'analysis_weaknesses'      => $analysis->weaknesses,
    'analysis_recommendations' => $analysis->recommendations,
];
```

**Why `AiAnalysis.input_hash` alone is insufficient:**

KAN-20's `AiAnalysis.input_hash` fingerprints the Offer AI input (name, description, payout, destination_url), not the actual generated analysis output. KAN-20 re-analysis reuses the same `AiAnalysis` row and may produce different score, summary, strengths, weaknesses, recommendations while the Offer input hash remains identical. Therefore the KAN-21 generation fingerprint must represent the EXACT immutable data sent to the content generator — including the analysis output fields — not just the analysis input hash.

## 7. Deterministic input_hash (staleness detection)

A SHA-256 `input_hash` is computed from the immutable generation input snapshot. This ensures generations become stale when Offer fields change OR when re-analysis produces different analysis output.

```
input_hash = sha256(canonical_json({
  "analysis_recommendations": [...],
  "analysis_score": 75,
  "analysis_strengths": [...],
  "analysis_summary": "...",
  "analysis_weaknesses": [...],
  "description": "...",
  "destination_url": "...",
  "name": "...",
  "payout": "25.00"
}))
```

- **Key order** is stable (alphabetical).
- **payout** is canonicalized to two decimal places.
- **description** is normalized consistently (null → empty string).
- **destination_url** is NOT lowercased (URL paths may be case-sensitive).
- **analysis_score** is integer.
- **analysis_strengths/weaknesses/recommendations** are arrays serialized as JSON.
- Changing `Offer.status` only does NOT change the hash.
- If re-analysis produces different analysis output, the hash changes → old generations become stale.

## 8. Stale Calculation

For a **completed** generation:

1. Load current Offer.
2. Load latest completed `AiAnalysis` for this Offer.
3. If analysis is missing, pending, processing, failed, or stale (via `OfferInputHasher`) → generation is stale.
4. Otherwise, rebuild the current generation input snapshot from Offer + analysis output.
5. Compute SHA-256 of that snapshot.
6. Compare to `generation.input_hash`. If different → stale.

For **non-completed** generations (pending/processing):

- `is_stale = false`. Do not mark a newly queued generation stale merely because `input_hash` has not yet been persisted.

## 9. Generation Behavior

Each trigger creates a **NEW row** (except idempotent duplicate during pending/processing). Do not reuse or update existing rows.

**State transitions:**

```
(no row or last row completed/failed) → INSERT new pending row
pending                               → return current, no Job
processing                            → return current, no Job
```

**Implementation:** Check for existing `pending`/`processing` generation. If found, return it (200). Otherwise, validate analysis is completed AND non-stale (422 if not). INSERT new pending row and dispatch Job.

**Job dispatch after commit:** The Job is dispatched only after the DB transaction commits.

## 10. API Contract

### POST /api/v1/offers/{offer}/generate

| Scenario | HTTP | Body | Job? |
|----------|------|------|------|
| Analysis completed AND non-stale, no pending/processing generation | 202 | new pending generation | yes |
| Existing generation is `pending` or `processing` | 200 | same generation (idempotent) | no |
| No analysis, pending, processing, failed, or stale | 422 | error message | no |

**422 message:** `"Une analyse IA terminée et à jour est requise avant de générer du contenu."`

### GET /api/v1/offers/{offer}/generations

| Scenario | HTTP |
|----------|------|
| Offer has generations | 200 with list |
| Offer has no generations | 200 with empty list |

### GET /api/v1/offers/{offer}/generations/{generation}

| Scenario | HTTP |
|----------|------|
| Generation exists and belongs to Offer | 200 with generation |
| Generation not found | 404 |

**Response JSON uses English domain keys:** `hooks`, `captions`. The values generated by the AI are in French.

## 11. Status Lifecycle

```
pending → processing → completed
                   ↘ failed
```

- `pending`: row created, Job queued or about to be dispatched.
- `processing`: Job has started execution, atomically transitioned from pending.
- `completed`: AI output validated and persisted.
- `failed`: unrecoverable error (missing key, invalid output, provider error after retries).

The Job's `failed()` method is the **only** place that may set `status = failed`. Never set `failed` on the first transient attempt — let retries happen.

**No re-generation via same-row reset.** Unlike KAN-20, KAN-21 creates new rows for fresh generations.

## 12. Queue Job (GenerateContentJob)

| Property | Value |
|----------|-------|
| Queue | default (database) |
| Tries | 3 TOTAL attempts |
| Timeout | 60s |
| failOnTimeout | true |
| Backoff | [30, 60] (excludes final attempt) |
| Unique | `ShouldBeUnique` (no manual Cache::lock) |

**Job payload:** `['generation_id' => $generation->id]` (not `offer_id`).

**Job execution flow:**
1. Load `AiGeneration` by ID.
2. If not found → return silently.
3. If status is `completed` or `failed` → return silently (terminal state).
4. Atomically transition status from `pending` to `processing` (or continue if already `processing` from a retry).
5. Load related Offer.
6. Load latest completed `AiAnalysis` for this Offer.
7. If no completed analysis, or analysis is stale → fail with safe message.
8. Build **one immutable** AI-input snapshot from: `name`, `description`, `payout`, `destination_url`, plus analysis output fields (`score`, `summary`, `strengths`, `weaknesses`, `recommendations`).
9. Compute `input_hash` from that exact snapshot.
10. Build AI prompt from the **same** snapshot.
11. Call Prism structured output.
12. Application validates returned structured result via domain validator before persistence.
13. Persist validated fields + `input_hash` + `provider` + `model` + `completed_at = now()`.
14. Set `status = completed`.

**failed() method:**
- Log safe message (no raw exception details).
- Set `status = failed`, `error_message = "La génération de contenu n'a pas pu être terminée. Veuillez réessayer."`.
- Do NOT persist raw provider response.

**Retry semantics:**
- **Transient provider failure:** keep status `processing` and rethrow so the same Job can retry. Do NOT permanently mark failed on the first transient attempt.
- **After final exhaustion:** `failed()` marks the generation failed.
- **Permanent configuration/output failure:** fail safely without unnecessary retries.

## 13. Validation Ownership

- **Prism structured schema** provides provider-level structure enforcement (array types, string constraints).
- **One application domain validator** runs after Prism returns, before persistence. Validates: hooks 3–5 items, each string max 200 chars, trimmed, non-empty; captions 3–5 items, each string max 500 chars, trimmed, non-empty.

Do not duplicate identical validation logic. Prism schema enforces structure; the domain validator enforces business rules.

## 14. Database Schema

### `ai_generations` table

```php
$table->id();

$table->foreignId('offer_id')
    ->constrained()
    ->cascadeOnDelete();

$table->string('language', 10)
    ->nullable()
    ->default('fr');

$table->string('tone', 50)->nullable();
$table->string('platform', 50)->nullable();

$table->json('hooks')->nullable();
$table->json('captions')->nullable();

$table->string('status', 20)
    ->default(AiProcessStatus::Pending->value)
    ->index();

$table->char('input_hash', 64)->nullable();

$table->string('provider', 50)->nullable();
$table->string('model', 100)->nullable();

$table->text('error_message')->nullable();

$table->timestamp('completed_at')->nullable();

$table->timestamps();
```

**Deviation from MLD:** Added `input_hash`, `provider`, `model` columns (same pattern as KAN-20). Added `language` column with default `'fr'` for future multi-language support. Removed `calls_to_action` column (TBD).

**No UNIQUE constraint on `offer_id`** — multiple generations per Offer (history model).

## 15. Security

- **Prompt injection protection:** Offer content + analysis results are injected into a system prompt that explicitly treats them as untrusted data. No instructions can be embedded in Offer fields.
- **No user credentials sent:** Only Offer fields + analysis results sent to AI.
- **No raw provider responses:** Not persisted, not logged with user context, not returned to client.
- **Safe error messages:** Only generic French messages returned (`"La génération de contenu n'a pas pu être terminée. Veuillez réessayer."`).
- **Rate limiting:** Existing `throttle:api` middleware.
- **Authorization:** `OfferPolicy::generate` ability (owner-only).

## 16. Async Missing-Provider Behavior

The provider call occurs asynchronously inside `GenerateContentJob`. The POST trigger always returns 202 on valid queue dispatch. If provider configuration is invalid when the Job executes:

- generation → `failed`
- Persist only: `"La génération de contenu n'a pas pu être terminée. Veuillez réessayer."`
- Do NOT expose: `"AI provider not configured"`, raw provider exceptions, credentials, stack traces.

Internal logging may record non-sensitive diagnostic context per existing logging conventions.

## 17. Postman/Newman

Create `postman/CPAFlow-AI-KAN-21.postman_collection.json` — must NOT require real AI completion. Verify: Health, Register/Login, Create Offer, Trigger → 202, GET generations → pending/processing, duplicate trigger → 200 same generation, unknown Offer → 404, foreign Offer → 403 where practical, guest → 401. Pest fake tests are authoritative for completed AI output.

## 18. Documentation

Update `docs/conception-technique.md` — change `ai_generations` from Planifié to Implémenté. Explain deviation from MLD (added `input_hash`, `provider`, `model` columns). Remove `calls_to_action` column (TBD).
