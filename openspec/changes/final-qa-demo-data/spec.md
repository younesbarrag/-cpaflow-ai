# Spec — KAN-23: Final QA, Test Stability & Demo Data

## 1. User Story

En tant qu'évaluateur/développeur, je veux une application déterministe, avec des tests stables et des données de démo réalistes, afin de pouvoir évaluer l'application et démontrer ses fonctionnalités de manière reproductible.

## 2. Acceptance Criteria

### AC-1: Flaky Test Eliminated
- **Given** le test `CampaignWebTest::success flash renders after campaign_creation`
- **When** exécuté 10 fois de suite
- **Then** il passe 10/10 fois (0 échec)

### AC-2: Factory Default State Deterministic
- **Given** un `OfferFactory::create()` sans state explicite
- **Then** le statut est toujours `draft`
- **And** aucun appel factory ne produit de statut aléatoire

### AC-3: Timing Tests Robustes
- **Given** le test `ConversionApiTest::generates converted_at as approximately the current time`
- **When** exécuté sous charge
- **Then** il utilise une tolérance de 5 secondes (pas 1 seconde)

### AC-4: Demo Seeder Safe and Idempotent
- **Given** `php artisan db:seed --class=DemoDataSeeder`
- **When** exécuté en production
- **Then** une exception est levée et aucun enregistrement n'est créé

### AC-5: Demo Seeder Idempotent
- **Given** `php artisan db:seed --class=DemoDataSeeder` exécuté deux fois
- **When** les comptes sont vérifiés
- **Then** les comptes démo existent (pas de doublons)
- **And** le nombre d'offres, campagnes, conversions reste identique

### AC-6: Demo Admin Account
- **Given** le compte `admin@example.test`
- **When** connecté via l'API
- **Then** peut lister les utilisateurs (200)
- **And** peut voir les détails d'un utilisateur (200)

### AC-7: Demo Affiliate Account
- **Given** le compte `affiliate@example.test`
- **When** connecté via l'API
- **Then** possède des offres, campagnes, et conversions liées

### AC-8: Demo Financial Consistency
- **Given** le dataset démo
- **When** `GetDashboardStatisticsAction::execute()` est appelé (all-time)
- **Then** le revenu approuvé est 50.00
- **And** les dépenses sont 70.00
- **And** le profit est -20.00

### AC-9: Full Test Suite Stable
- **Given** `php artisan test`
- **When** exécuté 3 fois de suite
- **Then** 600/600 passent à chaque fois

### AC-10: Newman E2E Collection Pass
- **Given** `npx newman run CPAFlow-AI-KAN-23.postman_collection.json`
- **When** exécuté après `php artisan db:seed --class=DemoDataSeeder`
- **Then** toutes les assertions passent (0 échec)

### AC-11: AI Demo Records Non-Stale
- **Given** les enregistrements AI démo
- **When** comparés aux snapshots de production
- **Then** les `input_hash` correspondent aux hash calculés par `OfferInputHasher` et `GenerationInputHasher`

### AC-12: DemoDataSeeder Pest Coverage
- **Given** `tests/Feature/Database/DemoDataSeederTest.php`
- **When** exécuté
- **Then** toutes les assertions passent

## 3. API Contract (Newman Collection)

### Health
- `GET /api/v1/health` → 200

### Authentication
- `POST /api/v1/auth/register` → 201
- `POST /api/v1/auth/login` → 200 + token

### Offers (QA User)
- `POST /api/v1/offers` → 201
- `GET /api/v1/offers` → 200

### Campaigns (QA User)
- `POST /api/v1/campaigns` → 201
- `POST /api/v1/campaigns/{id}/activate` → 200

### Tracking Link (QA User)
- `POST /api/v1/campaigns/{id}/tracking-links` → 201

### Conversion (QA User)
- `POST /api/v1/campaigns/{id}/conversions` → 201

### Expense (QA User)
- `POST /api/v1/campaigns/{id}/expenses` → 201

### Dashboard (QA User)
- `GET /api/v1/dashboard/statistics` → 200

### AI (Seeded Affiliate)
- `GET /api/v1/offers/{id}/analysis` → 200
- `GET /api/v1/offers/{id}/generations` → 200

### Admin (Demo Admin)
- `GET /api/v1/admin/users` → 200
- `GET /api/v1/admin/users/{id}` → 200

### Authorization
- `GET /api/v1/admin/users` (affiliate) → 403
- `GET /api/v1/admin/users` (guest) → 401

## 4. Validation Rules

### DemoDataSeeder
- Idempotent: `updateOrCreate` with deterministic identifiers
- No duplicate records on re-run
- No external API calls
- No queue dispatches
- Refuses to run in production environment

### Factory Defaults
- `OfferFactory`: default status = `draft`
- `UserFactory`: default role = `affiliate`
- `ConversionFactory`: lazy campaign reference
- `CampaignExpenseFactory`: lazy campaign reference

## 5. Migration Safety

**No migration will be created.**

MySQL/InnoDB already creates indexes on foreign key columns. Verified via `SHOW INDEX` on actual `cpaflow_ai` database.

No destructive commands will be used:
- No `migrate:fresh`
- No `migrate:reset`
- No `migrate:rollback`
- No `db:wipe`

## 6. Files Expected to Change

### Modified (7)
| File | Change |
|------|--------|
| `database/factories/OfferFactory.php` | Default → draft, add suspended/archived states |
| `database/factories/UserFactory.php` | Explicit role in definition |
| `database/factories/ConversionFactory.php` | Lazy campaign reference |
| `database/factories/CampaignExpenseFactory.php` | Lazy campaign reference |
| `tests/Feature/Web/CampaignWebTest.php` | Line 246: `->draft()->create()` |
| `tests/Feature/Api/V1/ConversionApiTest.php` | Timing tolerance → 5s |
| `docs/conception-technique.md` | Demo setup section |

### Created (4)
| File | Purpose |
|------|---------|
| `database/seeders/DemoDataSeeder.php` | Deterministic demo data |
| `tests/Feature/Database/DemoDataSeederTest.php` | Seeder test coverage |
| `postman/CPAFlow-AI-KAN-23.postman_collection.json` | Final E2E collection |
| `openspec/changes/final-qa-demo-data/*.md` (5 files) | Planning package |

### NOT Created
| Item | Reason |
|------|--------|
| FK index migration | MySQL already has these indexes |
| Conversion approval endpoint | Separate story |

## 7. Scope Exclusions

| Item | Reason |
|------|--------|
| Conversion approval/rejection endpoint | Project release blocker — separate story after KAN-23 |
| FK index migration | MySQL already auto-creates indexes — verified |
| Login rate limiting | Security improvement, separate concern |
| Tracking redirect rate limiting | Security improvement, separate concern |
| User deletion cascade fix | Architectural decision needed |
| Offer name uniqueness | Business decision needed |
| Blade UI redesign | KAN-31 Phase 1 sufficient |
| Production seeder changes | Demo data is local-only |
| CI modifications | KAN-25 stable |
| New business features | KAN-23 is QA/stability only |
