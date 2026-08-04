# Spec - KAN-20: Analyser une offre avec l'IA

## 1. User Story

En tant qu'affilié, je veux analyser une offre CPA avec l'IA pour en comprendre les points forts, les faiblesses et le potentiel avant de construire des campagnes.

## 2. Acceptance Criteria

### AC-1: Déclencher l'analyse
- **Given** je suis authentifié et propriétaire de l'Offre
- **When** je POST `/api/v1/offers/{offer}/analyze`
- **Then** je reçois 202 Accepted avec l'enregistrement d'analyse en statut `pending`
- **And** un AnalyzeOfferJob est dispatché à la queue (après commit DB)

### AC-2: Récupérer l'analyse
- **Given** je suis authentifié et l'Offre a une analyse terminée
- **When** je GET `/api/v1/offers/{offer}/analysis`
- **Then** je reçois 200 OK avec le résultat structuré de l'analyse

### AC-3: Analyse introuvable
- **Given** je suis authentifié et l'Offre n'a pas d'analyse
- **When** je GET `/api/v1/offers/{offer}/analysis`
- **Then** je reçois 404 Not Found

### AC-4: Déclencher un doublon (idempotence)
- **Given** je suis authentifié et une analyse est déjà `pending` ou `processing`
- **When** je POST `/api/v1/offers/{offer}/analyze`
- **Then** je reçois 200 OK avec l'analyse actuelle (aucun nouveau Job)

### AC-5: Ré-analyse
- **Given** je suis authentifié et une analyse est `completed` ou `failed`
- **When** je POST `/api/v1/offers/{offer}/analyze`
- **Then** la même ligne est mise à jour vers `pending`
- **And** un nouveau AnalyzeOfferJob est dispatché (après commit)

### AC-6: Détection d'obsolètes (stale)
- **Given** j'ai une analyse terminée et l'Offre a été modifiée après l'analyse
- **When** je GET `/api/v1/offers/{offer}/analysis`
- **Then** `is_stale` est `true` dans la réponse

### AC-7: Sécurité
- Invité → 401
- Offre étrangère → 403
- Offre inconnue → 404

### AC-8: Traitement asynchrone
- L'appel au fournisseur AI se produit dans le Job, pas dans la requête HTTP.
- Le déclenchement HTTP retourne immédiatement 202.

### AC-9: Sortie structurée
- La réponse AI doit correspondre au schéma défini : `score` (entier 0–100), `summary`, `strengths`, `weaknesses`, `recommendations`.
- Les clés de domaine/API sont en anglais. Les valeurs générées par l'IA sont en français.
- Sortie invalide → analyse marquée `failed`.

### AC-10: Sécurité financière
- L'analyse IA ne modifie jamais Offer.payout, Offer.status, ni aucune donnée financière.

### AC-11: input_hash (obsolètes)
- Un `input_hash` déterministe (SHA-256) est calculé à partir d'une représentation JSON canonique : `name`, `description`, `payout`, `destination_url`.
- `is_stale` se base sur la comparaison `input_hash`, pas sur `Offer.updated_at`.
- Modifier uniquement `Offer.status` ne rend PAS l'analyse obsolète.

### AC-12: URL limitation
- `destination_url` est envoyé comme texte brut uniquement.
- KAN-20 ne récupère PAS la page.
- L'IA ne peut PAS évaluer la qualité de la landing page, l'UX, ni le contenu réel de la page destination.

## 3. API Contract

### POST /api/v1/offers/{offer}/analyze

**Request:**
```
POST /api/v1/offers/5/analyze
Authorization: Bearer <token>
Accept: application/json
```

**Response 202 (nouvelle analyse):**
```json
{
  "data": {
    "id": 1,
    "offer_id": 5,
    "status": "pending",
    "created_at": "2026-08-03T12:00:00Z"
  }
}
```

**Response 200 (doublon — pending/processing):**
```json
{
  "data": {
    "id": 1,
    "offer_id": 5,
    "status": "processing",
    "created_at": "2026-08-03T12:00:00Z"
  }
}
```

**Response 202 (ré-analyse — completed/failed → pending):**
```json
{
  "data": {
    "id": 1,
    "offer_id": 5,
    "status": "pending",
    "created_at": "2026-08-03T12:00:00Z"
  }
}
```

### GET /api/v1/offers/{offer}/analysis

**Response 200:**
```json
{
  "data": {
    "id": 1,
    "offer_id": 5,
    "status": "completed",
    "score": 75,
    "summary": "Cette offre CPA cible la niche santé avec un paiement de 25€ compétitif. La page d'atterrissage proposée est claire mais manque d'optimisation mobile.",
    "strengths": [
      "Paiement compétitif pour la verticale santé",
      "Structure claire de la page d'atterrissage proposée"
    ],
    "weaknesses": [
      "Informations de ciblage géographique limitées",
      "Aucune optimisation mobile détectée sur l'URL fournie"
    ],
    "recommendations": [
      "Envisager des tests A/B sur les titres de la page d'atterrissage",
      "Ajouter des pixels de suivi pour l'optimisation des conversions"
    ],
    "is_stale": false,
    "completed_at": "2026-08-03T12:05:00Z",
    "created_at": "2026-08-03T12:00:00Z",
    "updated_at": "2026-08-03T12:05:00Z"
  }
}
```

**Response 404:**
```json
{
  "message": "No analysis found for this offer."
}
```

## 4. Database Schema

### `ai_analyses`

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

## 5. Job Configuration

### AnalyzeOfferJob

| Propriété | Valeur |
|-----------|--------|
| Queue | default (database) |
| Tries | 3 TOTAL |
| Timeout | 60s |
| failOnTimeout | true |
| Backoff | [30, 60] |
| Unique | `ShouldBeUnique` (no manual Cache::lock) |
| UniqueId | `uniqueId()` returns `offer_id` |

### Méthodes du Job

- `handle(AiAnalysis $analysis)` — traitement principal
- `failed(\Throwable $e)` — marquer comme échoué
- `backoff()` — retourne `[30, 60]`
- `uniqueId()` — retourne `offer_id`

### Flux d'exécution

1. Charger `AiAnalysis` par ID.
2. Si introuvable ou statut ≠ `processing` → retour silencieux.
3. Charger l'Offre associée.
4. Construire un snapshot immuable d'entrée AI : `name`, `description`, `payout`, `destination_url`.
5. Calculer `input_hash` à partir de ce snapshot exact.
6. Construire le prompt AI à partir du même snapshot.
7. Appeler Prism structured output.
8. L'application valide la sortie retournée avant la persistance.
9. Persister les champs validés + `input_hash` + `provider` + `model` + `completed_at = now()`.
10. Définir `status = completed`.

### Semantique de retry

- **Échec transitoire du fournisseur** : throw/retry ; NE PAS marquer échoué définitivement lors de la première tentative transitoire.
- **Après épuisement final** : `failed()` marque l'analyse comme échouée.
- **Échec permanent de configuration/sortie** : échouer en toute sécurité sans tentatives inutiles.

## 6. Validation Rules

### AiAnalysisRequest (trigger endpoint)

Aucun paramètre body requis. La validation est uniquement de propriété via `OfferPolicy::analyze`.

### Structured Output Validation (dans le Job)

Le fournisseur doit retourner :
- `score` : entier requis quand completed, 0–100
- `summary` : chaîne requise, max 1000 caractères
- `strengths` : tableau requis, 0–5 éléments, chaque chaîne max 200 caractères, trimée, non vide
- `weaknesses` : même contrainte
- `recommendations` : même contrainte

Les tableaux peuvent être vides.

La validation est effectuée deux fois :
1. Dans le Job (après l'appel Prism) — pour détecter les erreurs de format.
2. Avant la persistance — double vérification.

## 7. Messages d'erreur

| Erreur | HTTP | Message |
|--------|------|---------|
| Non authentifié | 401 | Unauthenticated |
| Offre étrangère | 403 | This action is unauthorized. |
| Offre inconnue | 404 | No route found... |
| Aucune analyse | 404 | No analysis found for this offer. |
| Échec fournisseur | 500 | L'analyse IA n'a pas pu être terminée. Veuillez réessayer. |
| Clé API manquante | 500 | AI provider not configured. |

## 8. Règles de sécurité

- Le prompt AI traite le contenu de l'Offre comme des données non fiables (pas des instructions).
- Aucun identifiant utilisateur, ID interne ou chemin de base de données envoyé au fournisseur.
- Aucune réponse brute du fournisseur retournée aux clients en production.
- La clé API n'est jamais journalisée ou exposée.
- Rate limiting via le middleware existant `throttle:api`.

## 9. Limites KAN-20 / KAN-21

| KAN-20 | KAN-21 |
|--------|--------|
| Analyse d'offre | Génération de contenu marketing |
| Score, summary, strengths, weaknesses, recommendations | Hooks, captions, appels à l'action |
| Une seule analyse par Offre | Plusieurs types de contenu par Offre |
| Sortie en lecture seule | Sortie générative |
| Clés de domaine en anglais, valeurs en français | TBD |
