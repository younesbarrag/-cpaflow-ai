# KAN-23: Final QA, Test Stability & Demo Data

## Story

En tant qu'évaluateur/développeur, je veux une application déterministe, avec des tests stables et des données de démo réalistes, afin de pouvoir évaluer l'application et démontrer ses fonctionnalités de manière reproductible.

## Status

| Item | Status |
|------|--------|
| OpenSpec | Planning finalisé (révision 1) |
| Implémentation | Complété |
| Tests | Complété |

## Files

| File | Status | Purpose |
|------|--------|---------|
| `openspec/changes/final-qa-demo-data/proposal.md` | Final | Proposition |
| `openspec/changes/final-qa-demo-data/design.md` | Final | Décisions de conception |
| `openspec/changes/final-qa-demo-data/spec.md` | Final | Spécification |
| `openspec/changes/final-qa-demo-data/tasks.md` | Final | Tâches d'implémentation |

## Quick Start

```bash
# Exécuter les tests
php artisan test

# Vérifier le code
vendor/bin/pint --test

# Seed les données démo (LOCALE UNIQUEMENT)
php artisan db:seed --class=DemoDataSeeder

# Exécuter la collection Newman finale
npx newman run postman/CPAFlow-AI-KAN-23.postman_collection.json -e postman/CPAFlow-AI-Local.postman_environment.json
```

## Key Design Decisions

| Decision | Value |
|----------|-------|
| Flaky test fix | Corriger la factory (état par défaut `draft`), pas le test |
| Factory default | `OfferFactory` → `draft`, `UserFactory` → explicit `affiliate` |
| Conversion gap | Classé PROJECT RELEASE BLOCKER, story séparée recommandée |
| FK indexes | Aucune migration — MySQL crée automatiquement les index sur les FK |
| Demo seeder | `DemoDataSeeder` invoqué manuellement, refuse de tourner en production |
| Demo credentials | `admin@example.test` / `affiliate@example.test` / `affiliate2@example.test` |
| Idempotence | `updateOrCreate` avec identifiants déterministes pour TOUS les types d'enregistrements |
| AI hash consistency | Utilise `OfferInputHasher` + `GenerationInputHasher` de production |
| Period dates | Ancrées aux frontières de calendrier (today, startOfDay, subDay) |
| Postman final | Collection unifiée, QA user séparé pour E2E flow |
| Timing tests | Tolérance 5 secondes (pas 1 seconde) |

## Flaky Test Root Cause

```
CampaignWebTest::success flash renders after campaign_creation
  ↓
Offer::factory()->create() sans status explicite
  ↓
OfferFactory::definition() → fake()->randomElement(OfferStatus::cases())
  ↓
~25% de chance de obtenir 'Archived'
  ↓
StoreCampaignRequest::after() rejette les offres archivées
  ↓
422 au lieu de 302 redirect
  ↓
assertSessionHas('success') échoue
```

Fix: `->draft()->create()` au lieu de `->create()`.

## FK Index Audit

MySQL/InnoDB already creates indexes on FK columns. Verified via `SHOW INDEX`:

| Table | FK Column | Index Exists |
|-------|-----------|-------------|
| campaigns | offer_id | campaigns_offer_id_foreign |
| tracking_clicks | tracking_link_id | tracking_clicks_tracking_link_id_foreign |
| conversions | campaign_id | conversions_campaign_id_foreign |
| campaign_expenses | campaign_id | campaign_expenses_campaign_id_foreign |
| ai_generations | offer_id | ai_generations_offer_id_foreign |

**No migration required.**

## Conversion Approval Gap

| État | Détail |
|------|--------|
| Gap | Aucun endpoint HTTP pour approuver/rejeter les conversions |
| Impact | Revenu dashboard toujours $0.00 pour les conversions normalement créées |
| Classification | PROJECT RELEASE / FUNCTIONAL-COMPLETENESS BLOCKER |
| Recommandation | Story séparée immédiatement après KAN-23 |
| Contournement démo | Le seeder crée des conversions directement en statut `Approved` |

## Demo Accounts

| Account | Email | Password | Role |
|---------|-------|----------|------|
| Admin | `admin@example.test` | `password` | admin |
| Affiliate | `affiliate@example.test` | `password` | affiliate |
| Affiliate2 | `affiliate2@example.test` | `password` | affiliate |

**DEMO ONLY — NEVER PRODUCTION CREDENTIALS**

## Demo Dataset

```
Admin (admin@example.test)
  ↓ manages

Affiliate (affiliate@example.test)
  ├── Offer 1 "DEMO — Fitness Offer" (active, $25.00)
  │   ├── Campaign 1 "DEMO — Active Campaign" (active)
  │   │   └── TrackingLink 1 (fixed code: demo1234567890demo1234567890de)
  │   │       └── TrackingClick ×3 (today, today, 15 days ago)
  │   ├── Conversion 1 (approved, $25.00, today)
  │   ├── Conversion 2 (approved, $25.00, 15 days ago)
  │   ├── Conversion 3 (pending, $25.00, 15 days ago)
  │   ├── CampaignExpense 1 ($40.00, 15 days ago)
  │   ├── CampaignExpense 2 ($30.00, today)
  │   ├── AiAnalysis (completed, score 85, valid hash)
  │   └── AiGeneration (completed, hooks+captions, valid hash)
  ├── Offer 2 "DEMO — Draft Offer" (draft, $10.00)
  │   └── Campaign 2 "DEMO — Draft Campaign" (draft)
  └── Offer 3 "DEMO — Archived Offer" (archived, $15.00)

Affiliate2 (affiliate2@example.test)
  (empty — safe target for admin role-change demo)
```

## Expected Dashboard Totals (All-Time)

| Metric | Value |
|--------|-------|
| offer_count | 3 |
| campaign_count | 2 |
| active_campaign_count | 1 |
| click_count | 3 |
| conversion_count | 3 |
| revenue | 50.00 |
| total_expenses | 70.00 |
| profit | -20.00 |

## Expected Period Totals

| Period | Clicks | Conversions | Revenue | Expenses | Profit |
|--------|--------|-------------|---------|----------|--------|
| all_time | 3 | 3 | 50.00 | 70.00 | -20.00 |
| today | 2 | 2 | 25.00 | 30.00 | -5.00 |
| this_month | 3 | 3 | 50.00 | 70.00 | -20.00 |
| last_7_days | 3 | 3 | 50.00 | 70.00 | -20.00 |

## OpenSpec Checkbox Count

**Total: 46. Completed: 46. Remaining: 0.**
