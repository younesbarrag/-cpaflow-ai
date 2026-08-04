# Proposal - KAN-21: Générer du contenu marketing avec l'IA

## 1. Summary

Add an AI-powered marketing content generation feature that accepts a `POST` trigger on an Offer, dispatches an asynchronous generation Job via `particle-academy/prism`, persists structured French-language output (hooks, captions) in a dedicated `ai_generations` table, and exposes `GET` endpoints to retrieve generation history. A deterministic `input_hash` (SHA-256 of an immutable snapshot containing Offer fields and analysis output fields) tracks staleness. Multiple generations per Offer are allowed (history model). Generation requires a completed AND current (non-stale) AI analysis as context input. The generated content is advisory only — it never modifies Offer data, financial values, or statuses.

## 2. Problem

Affiliate marketers need compelling hooks and captions to build high-converting CPA campaigns. After analyzing an offer (KAN-20), there is no automated way to generate ready-to-use marketing copy. Without AI-generated content, users must write hooks and captions from scratch, wasting time and often producing suboptimal copy. KAN-21 bridges the gap between offer analysis and campaign creation by generating marketing content informed by the analysis results.

## 3. Objectives

- Let an Offer owner trigger content generation via `POST /api/v1/offers/{offer}/generate`.
- Persist generation results in a dedicated `ai_generations` table (multiple rows per Offer).
- Return generations via `GET /api/v1/offers/{offer}/generations` (list) and `GET /api/v1/offers/{offer}/generations/{generation}` (detail).
- Use async Job processing with the `database` queue driver via `particle-academy/prism`.
- Validate provider output against a strict structured-output schema before persistence.
- Compute a deterministic `input_hash` (SHA-256) from an immutable snapshot of Offer fields + analysis output fields to detect staleness.
- Require a completed AND current (non-stale) AI analysis as context input for generation.
- Never modify Offer data, financial values, or statuses.
- Never send user credentials, internal IDs, or unrelated data to the AI provider.
- Preserve the existing 528/528 test baseline.

## 4. In Scope

- `ai_generations` migration (additive, with `language`, `tone`, `platform`, `hooks`, `captions`, `status`, `input_hash`, `provider`, `model` columns).
- `AiGeneration` model with `belongsTo` Offer relationship.
- `RequestContentGenerationAction` — race-safe trigger (DB transaction/locking), validates analysis is completed AND non-stale, creates pending record, dispatches Job after commit.
- `GetOfferGenerationsAction` — retrieves generation history with `is_stale` per generation via immutable-snapshot input_hash comparison.
- `GenerateContentJob` — calls AI provider via Prism, validates output, persists result.
- `AiGenerationRequest` — Form Request for trigger endpoint.
- `AiGenerationController` — API controller with `store`, `index`, and `show` methods.
- `AiGenerationResource` — JSON serialization (English domain keys, French values).
- `OfferPolicy::generate` ability.
- Prism structured schema + one application domain validator before persistence.
- Pest tests using Prism fake (zero real AI network calls).
- Documentation update.

## 5. Out of Scope

- Calls-to-action (CTA) generation — TBD for future scope.
- Multi-language output (KAN-21 is French only).
- Platform-specific prompt tuning (platform is metadata, not prompt-driven).
- Content editing/iteration UI.
- A/B testing of generated content.
- Campaign creation from generated content.
- Blade frontend for generation (API-only in KAN-21).
- Automatic Offer status changes based on generated content.
- Financial mutations by AI.
- AI usage billing / rate limiting.

## 6. Success Criteria

- Offer owner can trigger generation with completed AND non-stale analysis → 202 Accepted (new pending row).
- Generation requires completed AND non-stale analysis → 422 if analysis is missing, pending, processing, failed, or stale.
- Generation completes asynchronously → result persisted with `hooks` (3–5), `captions` (3–5).
- Offer owner can retrieve generation list → structured JSON with English domain keys, French values.
- Offer owner can retrieve specific generation → structured JSON.
- Foreign Offer → 403. Unknown Offer → 404. Guest → 401.
- Duplicate trigger during pending/processing → returns current generation with 200 OK, no new Job.
- After completed/failed generation, new trigger → NEW row (history model, not same-row reset).
- Stale detection via immutable-snapshot input_hash comparison (changing Offer fields or analysis output makes generations stale).
- All 528+ tests pass. Pint clean.
