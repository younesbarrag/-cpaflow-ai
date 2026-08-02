# CPAFlow AI — Backend

> Backend Laravel de l'application CPAFlow AI — SaaS d'affiliation marketing.

## Stack

| Composant | Technologie |
|-----------|-------------|
| Framework | Laravel 13.8 |
| PHP | 8.3+ |
| Base de données | MySQL (`cpaflow_ai`) |
| Auth web | Laravel Breeze (Blade, session) |
| Auth API | Sanctum Bearer Token |
| Frontend | Tailwind CSS + Alpine.js |
| Tests | Pest + SQLite in-memory |
| Style | Laravel Pint |

## Interfaces

| Interface | Préfixe | Format |
|-----------|---------|--------|
| Blade (web) | `/` | HTML |
| API REST | `/api/v1` | JSON |
| Tracking public | `/t/{code}` | 302 Redirect |

## Fonctionnalités implémentées

### Phase 1 — Données de base

- **Offres CPA** — CRUD + archivage, filtrage par statut, recherche par nom
- **Campagnes** — CRUD + lifecycle (`draft → active → suspended → active`)
- **Liens de tracking** — Génération de code unique, redirection publique 302
- **Clics de tracking** — Enregistrement avec hash IP (HMAC-SHA256), métadonnées UTM

### Interface web (KAN-31)

- Dashboard avec compteurs et données récentes
- Offres — Index paginé, création, édition, archivage
- Campagnes — Index paginé, création, édition, détail, activation, suspension
- Tracking links — Génération depuis la page détail campagne, copie clipboard
- Profil — Édition informations et mot de passe
- Auth — Login, register, mot de passe oublié
- Design system — Palette `brand`, composants Blade réutilisables
- Responsive — Tableaux desktop, cards mobile, menu hamburger

## Composants Blade réutilisables

`page-header` · `status-badge` · `flash-message` · `empty-state` · `confirm-button` · `search-input` · `tracking-url` · `form-group`

## Architecture

- **Controllers** — Coordination HTTP uniquement, pas de logique métier
- **Form Requests** — Validation réutilisée entre API et web
- **Actions** — Un cas métier par classe (`CreateOfferAction`, `ActivateCampaignAction`, etc.)
- **Policies** — Autorisation par ownership dérivée
- **Enums** — `OfferStatus`, `CampaignStatus`, `UserRole`

## Développement local

```bash
# Installer les dépendances
composer install
npm install

# Configuration
cp .env.example .env
php artisan key:generate

# Base de données
php artisan migrate

# Build frontend
npm run build

# Lancer le serveur
php artisan serve
```

## Tests

```bash
# Tous les tests
php artisan test

# Tests web uniquement
php artisan test tests/Feature/Web/

# Vérification style
vendor/bin/pint --test

# Routes
php artisan route:list
```

## Phase 1 — Exclusions

Les fonctionnalités suivantes ne font **pas** partie de la Phase 1 :

- Conversions (postback)
- Dépenses campagne
- Statistiques / analytics
- Intelligence artificielle (analyse, génération de contenu)
- Click analytics / visiteurs uniques
- Frontend admin
- Docker / Azure

## Licence

Propriétaire — CPAFlow AI
