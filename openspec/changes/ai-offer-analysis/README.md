# KAN-20: Analyser une offre avec l'IA

## Story

En tant qu'affilié, je veux analyser une offre CPA avec l'IA pour en comprendre les points forts, les faiblesses et le potentiel avant de construire des campagnes. L'analyse est uniquement consultative — elle ne modifie jamais les données de l'Offre, les valeurs financières ou les statuts.

## Status

| Item | Status |
|------|--------|
| OpenSpec | Planning finalisé |
| Implémentation | Terminée |
| Tests | Terminés |
| Postman | Terminé |

## Files

| File | Status | Purpose |
|------|--------|---------|
| `openspec/changes/ai-offer-analysis/proposal.md` | Created | Proposition |
| `openspec/changes/ai-offer-analysis/design.md` | Created | Décisions de conception |
| `openspec/changes/ai-offer-analysis/spec.md` | Created | Spécification |
| `openspec/changes/ai-offer-analysis/tasks.md` | Created | Tâches d'implémentation |

## Quick Start

```bash
# Installer le package AI
composer require particle-academy/prism

# Exécuter la migration
php artisan migrate

# Exécuter les tests
php artisan test --filter=AiAnalysisApiTest

# Exécuter Newman
npx newman run postman/CPAFlow-AI-KAN-20.postman_collection.json -e postman/CPAFlow-AI-Local.postman_environment.json
```

## KAN-20/KAN-21 Boundary

- **KAN-20:** Analyse d'offre (score, summary, strengths, weaknesses, recommendations)
- **KAN-21:** Génération de contenu marketing (hooks, captions, appels à l'action)

## Key Design Decisions

| Decision | Value |
|----------|-------|
| AI Provider | `particle-academy/prism` |
| Prism namespace | `Prism\Prism` (unchanged) |
| Score type | Entier 0–100 (requis pour completed) |
| Output keys | English domain keys (`score`, `summary`, `strengths`, `weaknesses`, `recommendations`) |
| Output values | French |
| AI input fields | name, description, payout, destination_url uniquement |
| URL fetching | Non — destination_url envoyé comme texte brut uniquement |
| Staleness | `input_hash` SHA-256 (pas `Offer.updated_at`) |
| input_hash algorithm | Canonical JSON, stable key order, no mb_strtolower/NFKC |
| Re-analysis | Réutilise la même ligne (pas delete/recreate) |
| Job dispatch | Après commit DB (`->afterCommit()`) |
| Unique job | `ShouldBeUnique` + `uniqueId()` (no manual Cache::lock) |
| Job payload | `analysis_id` (pas `offer_id`) |
| Raw provider response | Jamais persisté |
| DB schema | Explicit columns (summary, strengths, weaknesses, recommendations) — not opaque JSON blob |
| Configuration | `AI_PROVIDER`, `AI_MODEL`, provider-specific keys (e.g. `OPENAI_API_KEY`) |
| Safe errors | Generic French message only |
| Testing | Prism fake / StructuredResponseFake — zero real AI network calls |

## Migration Deviation from MLD

KAN-20 uses explicit columns (`summary`, `strengths`, `weaknesses`, `recommendations`) instead of the older MLD `resultats JSON` blob. Rationale: KAN-20 has a stable structured domain contract, so typed queries, explicit nullability, and individual field indexing are preferred.

## OpenSpec Checkbox Count

**Total: 63. Completed: 63. Remaining: 0.**
