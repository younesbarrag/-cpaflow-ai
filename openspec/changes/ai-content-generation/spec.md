# Spec - KAN-21: Générer du contenu marketing avec l'IA

## 1. User Story

En tant qu'affilié, je veux générer du contenu marketing (hooks, captions) avec l'IA pour une offre CPA analysée afin de disposer de textes prêts pour mes campagnes.

## 2. Acceptance Criteria

### AC-1: Déclencher la génération
- **Given** je suis authentifié et propriétaire de l'Offre
- **And** l'Offre a une analyse IA terminée (statut `completed`) ET à jour (non obsolète)
- **When** je POST `/api/v1/offers/{offer}/generate`
- **Then** je reçois 202 Accepted avec l'enregistrement de génération en statut `pending`
- **And** un GenerateContentJob est dispatché à la queue (après commit DB)

### AC-2: Récupérer la liste des générations
- **Given** je suis authentifié et l'Offre a des générations
- **When** je GET `/api/v1/offers/{offer}/generations`
- **Then** je reçois 200 OK avec la liste des générations triées par `created_at DESC`

### AC-3: Récupérer une génération spécifique
- **Given** je suis authentifié et la génération appartient à l'Offre
- **When** je GET `/api/v1/offers/{offer}/generations/{generation}`
- **Then** je reçois 200 OK avec le résultat structuré de la génération

### AC-4: Génération introuvable
- **Given** je suis authentifié et la génération n'existe pas
- **When** je GET `/api/v1/offers/{offer}/generations/{generation}`
- **Then** je reçois 404 Not Found

### AC-5: Déclencher un doublon (idempotence)
- **Given** je suis authentifié et une génération est déjà `pending` ou `processing`
- **When** je POST `/api/v1/offers/{offer}/generate`
- **Then** je reçois 200 OK avec la génération actuelle (aucun nouveau Job)

### AC-6: Nouvelle génération après complétion
- **Given** je suis authentifié et la dernière génération est `completed` ou `failed`
- **And** l'Offre a une analyse IA terminée ET à jour
- **When** je POST `/api/v1/offers/{offer}/generate`
- **Then** une NOUVELLE ligne est créée en statut `pending`
- **And** un nouveau GenerateContentJob est dispatché (après commit)

### AC-7: Analyse requise et à jour
- **Given** je suis authentifié
- **When** je POST `/api/v1/offers/{offer}/generate`
- **Then** je reçois 422 Unprocessable Entity si:
  - Aucune analyse IA n'existe pour cette Offre, OU
  - L'analyse est en statut `pending`, OU
  - L'analyse est en statut `processing`, OU
  - L'analyse est en statut `failed`, OU
  - L'analyse est `completed` mais obsolète par rapport à l'Offre actuelle
- **And** le message est: `"Une analyse IA terminée et à jour est requise avant de générer du contenu."`

### AC-8: Détection d'obsolètes (stale)
- **Given** j'ai une génération terminée
- **And** un snapshot reconstruit depuis l'Offre actuelle + l'analyse actuelle (si terminée et non obsolète) produit un hash différent du `input_hash` de la génération
- **When** je GET `/api/v1/offers/{offer}/generations/{generation}`
- **Then** `is_stale` est `true` dans la réponse

### AC-9: Sécurité
- Invité → 401
- Offre étrangère → 403
- Offre inconnue → 404

### AC-10: Traitement asynchrone
- L'appel au fournisseur AI se produit dans le Job, pas dans la requête HTTP.
- Le déclenchement HTTP retourne immédiatement 202.

### AC-11: Sortie structurée
- La réponse AI doit correspondre au schéma défini : `hooks` (tableau 3–5 éléments), `captions` (tableau 3–5 éléments).
- Les clés de domaine/API sont en anglais. Les valeurs générées par l'IA sont en français.
- Sortie invalide → génération marquée `failed`.

### AC-12: Sécurité financière
- La génération IA ne modifie jamais Offer.payout, Offer.status, ni aucune donnée financière.

### AC-13: input_hash (détection d'obsolètes)
- Un `input_hash` déterministe (SHA-256) est calculé à partir d'un snapshot immuable contenant :
  - Offer: `name`, `description`, `payout`, `destination_url`
  - Analysis: `score`, `summary`, `strengths`, `weaknesses`, `recommendations`
- Le même snapshot est utilisé pour le hash ET le prompt Prism.
- `is_stale` se base sur la comparaison du hash reconstruit vs `generation.input_hash`.
- Modifier uniquement `Offer.status` ne rend PAS la génération obsolète.
- Si une ré-analyse produit une sortie d'analyse différente, les générations précédentes deviennent obsolètes.

### AC-14: Historique
- Si une génération est `pending` ou `processing`, un nouveau déclencher retourne la génération existante (idempotence, AC-5).
- Si la dernière génération est `completed` ou `failed`, un nouveau déclencher crée une NOUVELLE ligne.
- L'endpoint GET retourne toutes les générations de l'Offre.

### AC-15: Aucune donnée brute
- La réponse brute du fournisseur n'est jamais persistée.
- La clé API n'est jamais journalisée ou exposée.

### AC-16: Lifecycle du Job
- Le Job transitionne atomiquement `pending` → `processing` au démarrage.
- En cas de retry, le Job continue depuis `processing`.
- `completed` et `failed` sont des états terminaux — le Job ne régénère pas.

## 3. API Contract

### POST /api/v1/offers/{offer}/generate

**Request:**
```
POST /api/v1/offers/5/generate
Authorization: Bearer <token>
Accept: application/json
```

**Response 202 (nouvelle génération):**
```json
{
  "data": {
    "id": 1,
    "offer_id": 5,
    "status": "pending",
    "language": "fr",
    "tone": null,
    "platform": null,
    "created_at": "2026-08-04T12:00:00Z"
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
    "language": "fr",
    "tone": null,
    "platform": null,
    "created_at": "2026-08-04T12:00:00Z"
  }
}
```

**Response 422 (analyse manquante ou obsolète):**
```json
{
  "message": "Une analyse IA terminée et à jour est requise avant de générer du contenu."
}
```

### GET /api/v1/offers/{offer}/generations

**Response 200:**
```json
{
  "data": [
    {
      "id": 2,
      "offer_id": 5,
      "status": "completed",
      "language": "fr",
      "tone": null,
      "platform": null,
      "hooks": [
        "Perdez du poids sans effort avec cette solution prouvée",
        "Votre corps mérite cette transformation en 30 jours",
        "La méthode secrète que les professionnels utilisent"
      ],
      "captions": [
        "Vous cherchez une solution efficace pour perdre du poids ? Cette offre CPA vous permet de recommander un produit de qualité à votre audience. Avec un paiement de 25€ par conversion, c'est l'opportunité parfaite pour monetiser votre contenu.",
        "Transformez votre vie en quelques clics ! Découvrez cette offre exclusive qui a déjà aidé des milliers de personnes. Partagez-la avec votre audience et gagnez à chaque inscription.",
        "Envie de générer des revenus complémentaires ? Ce programme d'affiliation vous offre tout ce qu'il faut pour réussir. Paiement rapide, support dédié, et produits convertissant."
      ],
      "is_stale": false,
      "completed_at": "2026-08-04T12:05:00Z",
      "created_at": "2026-08-04T12:00:00Z",
      "updated_at": "2026-08-04T12:05:00Z"
    },
    {
      "id": 1,
      "offer_id": 5,
      "status": "completed",
      "language": "fr",
      "tone": null,
      "platform": null,
      "hooks": [
        "Découvrez l'offre qui cartonne en ce moment",
        "Gagnez de l'argent avec cette astuce simple",
        "L'opportunité que vous attendiez depuis des mois"
      ],
      "captions": [
        "Voici une offre CPA à ne pas manquer. Avec un paiement de 25€ par conversion, c'est une opportunité en or pour les affiliés. Testez-la dès maintenant et maximisez vos revenus.",
        "Rejoignez des milliers d'affiliés qui génèrent déjà des revenus avec cette offre. Inscription simple, support réactif, et paiements rapides. Ne manquez pas cette chance.",
        "Envie de diversifier vos sources de revenus ? Cette offre CPA est faite pour vous. Produit de qualité, taux de conversion élevé, et commission attractive à chaque inscription."
      ],
      "is_stale": true,
      "completed_at": "2026-08-03T12:05:00Z",
      "created_at": "2026-08-03T12:00:00Z",
      "updated_at": "2026-08-03T12:05:00Z"
    }
  ]
}
```

### GET /api/v1/offers/{offer}/generations/{generation}

**Response 200:**
```json
{
  "data": {
    "id": 1,
    "offer_id": 5,
    "status": "completed",
    "language": "fr",
    "tone": null,
    "platform": null,
    "hooks": [
      "Découvrez l'offre qui cartonne en ce moment",
      "Gagnez de l'argent avec cette astuce simple",
      "L'opportunité que vous attendiez depuis des mois"
    ],
    "captions": [
      "Voici une offre CPA à ne pas manquer. Avec un paiement de 25€ par conversion, c'est une opportunité en or pour les affiliés. Testez-la dès maintenant et maximisez vos revenus.",
      "Rejoignez des milliers d'affiliés qui génèrent déjà des revenus avec cette offre. Inscription simple, support réactif, et paiements rapides. Ne manquez pas cette chance.",
      "Envie de diversifier vos sources de revenus ? Cette offre CPA est faite pour vous. Produit de qualité, taux de conversion élevé, et commission attractive à chaque inscription."
    ],
    "is_stale": false,
    "completed_at": "2026-08-04T12:05:00Z",
    "created_at": "2026-08-04T12:00:00Z",
    "updated_at": "2026-08-04T12:05:00Z"
  }
}
```

**Response 404:**
```json
{
  "message": "No generation found for this offer."
}
```

## 4. Database Schema

### `ai_generations`

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

## 5. Job Configuration

### GenerateContentJob

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

- `handle(AiGeneration $generation)` — traitement principal
- `failed(\Throwable $e)` — marquer comme échoué
- `backoff()` — retourne `[30, 60]`
- `uniqueId()` — retourne `offer_id`

### Flux d'exécution

1. Charger `AiGeneration` par ID.
2. Si introuvable → retour silencieux.
3. Si statut est `completed` ou `failed` → retour silencieux (état terminal).
4. Transition atomique du statut `pending` → `processing` (ou continuer si déjà `processing` depuis un retry).
5. Charger l'Offre associée.
6. Charger la dernière analyse IA terminée pour cette Offre.
7. Si aucune analyse terminée, ou analyse obsolète → échouer avec message sûr.
8. Construire **un snapshot immuable** d'entrée AI : `name`, `description`, `payout`, `destination_url`, plus champs de sortie d'analyse (`score`, `summary`, `strengths`, `weaknesses`, `recommendations`).
9. Calculer `input_hash` à partir de ce snapshot exact.
10. Construire le prompt AI à partir du **même** snapshot.
11. Appeler Prism structured output.
12. L'application valide la sortie retournée via un validateur de domaine avant la persistance.
13. Persister les champs validés + `input_hash` + `provider` + `model` + `completed_at = now()`.
14. Définir `status = completed`.

### Semantique de retry

- **Échec transitoire du fournisseur** : garder le statut `processing` et rethrow pour que le même Job puisse réessayer. NE PAS marquer échoué définitivement lors de la première tentative transitoire.
- **Après épuisement final** : `failed()` marque la génération comme échouée.
- **Échec permanent de configuration/sortie** : échouer en toute sécurité sans tentatives inutiles.

## 6. Validation Rules

### AiGenerationRequest (trigger endpoint)

Aucun paramètre body requis. La validation est uniquement de propriété via `OfferPolicy::generate`.

### Structured Output Validation

**Prism structured schema** fournit l'application de structure au niveau du fournisseur (types array, contraintes string).

**Un validateur de domaine applicatif** s'exécute après le retour de Prism, avant la persistance. Validation métier :
- `hooks` : tableau requis, 3–5 éléments, chaque chaîne max 200 caractères, trimée, non vide
- `captions` : tableau requis, 3–5 éléments, chaque chaîne max 500 caractères, trimée, non vide

Les tableaux ne peuvent pas être vides.

La validation en deux étapes (Prism + domaine) n'est pas une duplication : Prism enforce la structure, le validateur enforce les règles métier.

## 7. Messages d'erreur

| Erreur | HTTP | Message |
|--------|------|---------|
| Non authentifié | 401 | Unauthenticated |
| Offre étrangère | 403 | This action is unauthorized. |
| Offre inconnue | 404 | No route found... |
| Analyse manquante/obsolète | 422 | Une analyse IA terminée et à jour est requise avant de générer du contenu. |
| Aucune génération | 404 | No generation found for this offer. |
| Échec fournisseur (async) | — | La génération de contenu n'a pas pu être terminée. Veuillez réessayer. |

## 8. Règles de sécurité

- Le prompt AI traite le contenu de l'Offre et les résultats d'analyse comme des données non fiables (pas des instructions).
- Aucun identifiant utilisateur, ID interne ou chemin de base de données envoyé au fournisseur.
- Aucune réponse brute du fournisseur retournée aux clients en production.
- La clé API n'est jamais journalisée ou exposée.
- Rate limiting via le middleware existant `throttle:api`.

## 9. input_hash Calculation

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
