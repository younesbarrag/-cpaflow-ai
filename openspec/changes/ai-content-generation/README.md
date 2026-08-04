# KAN-21: Générer du contenu marketing avec l'IA

## Story

En tant qu'affilié, je veux générer du contenu marketing (hooks, captions) avec l'IA pour une offre CPA analysée afin de disposer de textes prêts pour mes campagnes.

## Status

| Item | Status |
|------|--------|
| OpenSpec | Terminé |
| Implémentation | Terminée |
| Tests | Terminés (42/42 PASS) |

## Files

| File | Status | Purpose |
|------|--------|---------|
| `openspec/changes/ai-content-generation/proposal.md` | Created | Proposition |
| `openspec/changes/ai-content-generation/design.md` | Created | Décisions de conception |
| `openspec/changes/ai-content-generation/spec.md` | Created | Spécification |
| `openspec/changes/ai-content-generation/tasks.md` | Created | Tâches d'implémentation |

## Quick Start

```bash
# Exécuter la migration
php artisan migrate

# Exécuter les tests
php artisan test --filter=AiGenerationApiTest

# Exécuter Newman
npx newman run postman/CPAFlow-AI-KAN-21.postman_collection.json -e postman/CPAFlow-AI-Local.postman_environment.json
```

## KAN-20/KAN-21 Boundary

- **KAN-20:** Analyse d'offre (score, summary, strengths, weaknesses, recommendations) — UNIQUE(offer_id), re-analysis REUSES same row/stable ID
- **KAN-21:** Génération de contenu marketing (hooks, captions) — Multiple rows per Offer (history model), each trigger creates NEW row

## Key Design Decisions

| Decision | Value |
|----------|-------|
| AI Provider | `particle-academy/prism` (reuse KAN-20) |
| History model | Multiple generations per Offer (no UNIQUE on offer_id) |
| Idempotency | Only blocks during pending/processing; new rows after completed/failed |
| Output keys | English domain keys (`hooks`, `captions`) |
| Output values | French |
| AI input fields | Offer: name, description, payout, destination_url + Analysis: score, summary, strengths, weaknesses, recommendations |
| URL fetching | Non — destination_url envoyé comme texte brut uniquement |
| Staleness | `input_hash` SHA-256 of immutable snapshot (Offer fields + analysis output fields) |
| input_hash algorithm | Canonical JSON, stable key order, no mb_strtolower/NFKC |
| Analysis prerequisite | Completed AND non-stale (via OfferInputHasher) |
| 422 message | "Une analyse IA terminée et à jour est requise avant de générer du contenu." |
| Job dispatch | Après commit DB (`->afterCommit()`) |
| Unique job | `ShouldBeUnique` + `uniqueId()` (no manual Cache::lock) |
| Job payload | `generation_id` (pas `offer_id`) |
| Job lifecycle | pending → atomically processing → completed/failed |
| Raw provider response | Jamais persisté |
| DB schema | hooks + captions JSON columns + input_hash, provider, model |
| Re-generation | NEW row (history, not same-row reset) |
| Validation | Prism structured schema + one application domain validator |
| Safe errors | Generic French message only |
| Missing provider (async) | generation → failed, POST still 202 |
| Testing | Prism fake / StructuredResponseFake — zero real AI network calls |

## Database Schema Deviation from MLD

KAN-21 adds `input_hash`, `provider`, `model` columns (same pattern as KAN-20). Added `language` column with default `'fr'` for future multi-language support. Removed `calls_to_action` column (TBD). No UNIQUE constraint on `offer_id` (history model).

## OpenSpec Checkbox Count

**Total: 67. Completed: 67. Remaining: 0.**
