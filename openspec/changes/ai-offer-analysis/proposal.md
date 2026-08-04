# Proposal - KAN-20: Analyser une offre avec l'IA

## 1. Summary

Add an AI-powered offer analysis feature that accepts a `POST` trigger on an Offer, dispatches an asynchronous analysis Job via `particle-academy/prism`, persists a structured French-language analysis result (score, summary, strengths, weaknesses, recommendations) in a dedicated `ai_analyses` table, and exposes a `GET` endpoint to retrieve the current analysis. A deterministic `input_hash` (SHA-256 of canonical Offer fields) tracks staleness. Re-analysis reuses the same row. The analysis is advisory only — it never modifies Offer data, financial values, or statuses.

## 2. Problem

Affiliate marketers evaluate CPA offers manually. There is no automated way to assess an offer's attractiveness, conversion potential, or campaign readiness. Without structured AI analysis, users rely on intuition, missing potential issues (low payout, unclear value proposition) or opportunities (high-converting niches, competitive payouts). KAN-20 adds a first AI capability to CPAFlow — offer analysis — while keeping generation (hooks, captions) for KAN-21.

## 3. Objectives

- Let an Offer owner trigger an AI analysis via `POST /api/v1/offers/{offer}/analyze`.
- Persist the analysis result in a dedicated `ai_analyses` table with `UNIQUE(offer_id)`.
- Return the analysis via `GET /api/v1/offers/{offer}/analysis`.
- Use async Job processing with the `database` queue driver via `particle-academy/prism`.
- Validate provider output against a strict structured-output schema before persistence.
- Compute a deterministic `input_hash` (SHA-256) from canonical `name`, `description`, `payout`, `destination_url` to detect staleness.
- Never modify Offer data, financial values, or statuses.
- Never send user credentials, internal IDs, or unrelated data to the AI provider.
- Preserve the existing 490/490 test baseline.

## 4. In Scope

- `ai_analyses` migration (additive, with `input_hash`, `provider`, `model` columns).
- `AiAnalysis` model with `belongsTo` Offer relationship.
- `RequestOfferAnalysisAction` — race-safe trigger (DB transaction/locking), creates/resets pending record, dispatches Job after commit.
- `GetOfferAnalysisAction` — retrieves current analysis with `is_stale` via `input_hash` comparison.
- `AnalyzeOfferJob` — calls AI provider via Prism, validates output, persists result.
- `AiAnalysisRequest` — Form Request for trigger endpoint.
- `AiAnalysisController` — API controller with `analyze` and `show` methods.
- `AiAnalysisResource` — JSON serialization (English domain keys, French values).
- `OfferPolicy::analyze` ability.
- Structured output schema validation (twice: in Job and before persistence).
- Pest tests using Prism fake (zero real AI network calls).
- Postman/Newman collection (no real provider required).
- Documentation update.

## 5. Out of Scope

- KAN-21 hooks, captions, ad copy, generated marketing content.
- Multiple AI providers selectable by users.
- Prompt editor UI.
- Web scraping / external URL fetching (destination_url is NOT fetched — sent as plain text only).
- AI usage billing / rate limiting.
- Chat interface.
- Vector database / embeddings / RAG / fine-tuning.
- Blade frontend for analysis (API-only in KAN-20).
- Automatic Offer status changes based on AI score.
- Financial mutations by AI.

## 6. Success Criteria

- Offer owner can trigger analysis → 202 Accepted (new row reset to pending).
- Analysis completes asynchronously → result persisted with `score`, `summary`, `strengths`, `weaknesses`, `recommendations`.
- Offer owner can retrieve analysis → structured JSON with English domain keys, French values.
- Foreign Offer → 403. Unknown Offer → 404. Guest → 401.
- Duplicate trigger during pending/processing → returns current analysis with 200 OK, no new Job.
- Re-analysis after completed/failed → same row reset to pending → 202.
- Stale detection via `input_hash` comparison (changing only Offer.status does NOT make analysis stale).
- All 490+ tests pass. Pint clean.
