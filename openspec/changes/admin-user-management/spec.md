# Spec - KAN-22: Administration des utilisateurs

## 1. User Story

En tant qu'administrateur, je veux gérer les comptes utilisateurs (lister, consulter, modifier le rôle) afin de maintenir la gouvernance de la plateforme CPAFlow.

## 2. Acceptance Criteria

### AC-1: Lister les utilisateurs
- **Given** je suis authentifié en tant qu'Admin
- **When** je GET `/api/v1/admin/users`
- **Then** je reçois 200 OK avec une liste paginée de tous les utilisateurs
- **And** chaque utilisateur affiche: id, name, email, role, email_verified_at, created_at, updated_at
- **And** les champs sensibles (password, remember_token) ne sont jamais exposés

### AC-2: Recherche par nom/email
- **Given** je suis Admin
- **When** je GET `/api/v1/admin/users?search=jane`
- **Then** je reçois 200 OK avec les utilisateurs dont le nom ou l'email contient "jane" (case-insensitivity depends on DB collation)

### AC-3: Filtre par rôle
- **Given** je suis Admin
- **When** je GET `/api/v1/admin/users?role=admin`
- **Then** je reçois 200 OK avec uniquement les utilisateurs ayant le rôle "admin"

### AC-4: Recherche et filtre combinés
- **Given** je suis Admin
- **When** je GET `/api/v1/admin/users?search=jane&role=affiliate`
- **Then** je reçois 200 OK avec les utilisateurs correspondant aux deux critères

### AC-5: Consulter un utilisateur
- **Given** je suis Admin et l'utilisateur existe
- **When** je GET `/api/v1/admin/users/{user}`
- **Then** je reçois 200 OK avec les détails de l'utilisateur

### AC-6: Utilisateur introuvable
- **Given** je suis Admin
- **When** je GET `/api/v1/admin/users/99999`
- **Then** je reçois 404 Not Found

### AC-7: Modifier le rôle d'un utilisateur
- **Given** je suis Admin et l'utilisateur n'est pas moi-même
- **And** le rôle demandé est différent du rôle actuel
- **And** le rôle cible est valide (affiliate ou admin)
- **And** si l'utilisateur cible est Admin et la mutation le retirerait du rôle Admin, il doit rester au moins un Admin après la mutation (vérifié dans une transaction avec verrouillage de l'ensemble des Admin)
- **When** je PATCH `/api/v1/admin/users/{user}` avec `{"role": "affiliate"}`
- **Then** je reçois 200 OK avec l'utilisateur mis à jour

### AC-8: Rôle invalide
- **Given** je suis Admin
- **When** je PATCH `/api/v1/admin/users/{user}` avec `{"role": "superadmin"}`
- **Then** je reçois 422 Unprocessable Entity

### AC-9: Auto-demption interdite
- **Given** je suis Admin
- **When** je PATCH `/api/v1/admin/users/{auth()->id()}` avec `{"role": "affiliate"}`
- **Then** je reçois 403 Forbidden
- **And** la vérification est effectuée par le UserPolicy (couche autorisation)

### AC-10: Dernier Admin protégé
- **Given** je suis le seul Admin de l'application
- **When** un Admin tente de changer mon rôle en "affiliate"
- **Then** je reçois 409 Conflict avec le message "Cannot demote the last administrator."
- **And** la vérification est effectuée par l'UpdateUserRoleAction dans une transaction avec verrouillage de l'ensemble des Admin (couche invariant métier)

### AC-11: Même rôle — pas d'effet
- **Given** je suis Admin
- **When** je PATCH `/api/v1/admin/users/{user}` avec `{"role": "admin"}` et que l'utilisateur est déjà admin
- **Then** je reçois 200 OK avec l'utilisateur (aucune mutation effective)

### AC-12: Sécurité — Invité
- **Given** je ne suis pas authentifié
- **When** j'appelle un endpoint admin
- **Then** je reçois 401 Unauthorized

### AC-13: Sécurité — Utilisateur non-admin
- **Given** je suis authentifié en tant qu'Affiliate
- **When** j'appelle un endpoint admin
- **Then** je reçois 403 Forbidden

### AC-14: Aucun contournement business
- **Given** je suis Admin
- **When** j'essaie d'accéder aux Offres/Campagnes d'un autre utilisateur via les endpoints existants
- **Then** je reçois 403 Forbidden (les policies Offer/Campaign restent ownership-based)

### AC-15: Pas d'escalade de privilèges
- L'endpoint d'inscription n'accepte pas de champ `role`.
- L'endpoint de profil n'accepte pas de champ `role`.
- Un utilisateur ne peut pas devenir Admin via l'API publique.

### AC-16: Sécurité du corps de requête
- Le PATCH `/api/v1/admin/users/{user}` n'accepte que le champ `role`.
- Les champs `id`, `name`, `email`, `password`, `remember_token` ne sont pas consommés par cet endpoint.

## 3. API Contract

### GET /api/v1/admin/users

**Request:**
```
GET /api/v1/admin/users?search=jane&role=affiliate&page=1
Authorization: Bearer <admin-token>
Accept: application/json
```

**Response 200:**
```json
{
  "data": [
    {
      "id": 2,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "affiliate",
      "email_verified_at": "2026-07-20T12:00:00Z",
      "created_at": "2026-07-20T12:00:00Z",
      "updated_at": "2026-07-20T12:00:00Z"
    }
  ],
  "links": {
    "first": "http://localhost/api/v1/admin/users?page=1",
    "last": "http://localhost/api/v1/admin/users?page=5",
    "prev": null,
    "next": "http://localhost/api/v1/admin/users?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "http://localhost/api/v1/admin/users",
    "per_page": 15,
    "to": 15,
    "total": 70
  }
}
```

### GET /api/v1/admin/users/{user}

**Response 200:**
```json
{
  "data": {
    "id": 2,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "affiliate",
    "email_verified_at": "2026-07-20T12:00:00Z",
    "created_at": "2026-07-20T12:00:00Z",
    "updated_at": "2026-07-20T12:00:00Z"
  }
}
```

**Response 404:**
```json
{
  "message": "No query results for model [App\\Models\\User]."
}
```

### PATCH /api/v1/admin/users/{user}

**Request:**
```
PATCH /api/v1/admin/users/2
Authorization: Bearer <admin-token>
Content-Type: application/json
Accept: application/json

{
  "role": "affiliate"
}
```

**Response 200:**
```json
{
  "data": {
    "id": 2,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "affiliate",
    "email_verified_at": "2026-07-20T12:00:00Z",
    "created_at": "2026-07-20T12:00:00Z",
    "updated_at": "2026-08-04T12:00:00Z"
  }
}
```

**Response 403 (self-demotion or non-admin):**
```json
{
  "message": "Forbidden."
}
```

**Response 409 (last admin demotion):**
```json
{
  "message": "Cannot demote the last administrator."
}
```

**Response 422 (invalid role):**
```json
{
  "message": "The selected role is invalid.",
  "errors": {
    "role": ["The selected role is invalid."]
  }
}
```

## 4. Validation Rules

### UpdateUserRoleRequest

| Field | Rules |
|-------|-------|
| `role` | required, string, in:affiliate,admin |

The `in` rule validates against `UserRole::cases()` values. No arbitrary strings accepted.

### Authorization (Policy layer)

- Self-demotion: `$actor->id === $target->id` → 403 (via `UserPolicy::updateRole`)

### Domain invariant (Action layer)

- Last-admin: transaction-safe check inside `UpdateUserRoleAction` → 409

## 5. Authorization Responsibility Split

| Check | Layer | Error |
|-------|-------|-------|
| Non-admin caller | `EnsureUserIsAdmin` middleware | 403 |
| Self-demotion | `UserPolicy::updateRole` | 403 |
| Last-admin invariant | `UpdateUserRoleAction` | 409 |
| Invalid role value | `UpdateUserRoleRequest` validation | 422 |

## 6. Messages d'erreur

| Erreur | HTTP | Message | Couche |
|--------|------|---------|--------|
| Non authentifié | 401 | Unauthenticated | Middleware |
| Non-admin | 403 | Forbidden. | Middleware |
| Auto-demotion | 403 | Forbidden. | Policy |
| Utilisateur inconnu | 404 | No query results for model [App\Models\User]. | Laravel |
| Rôle invalide | 422 | The selected role is invalid. | FormRequest |
| Dernier Admin | 409 | Cannot demote the last administrator. | Action |

## 7. Concurrency Safety

### Race condition addressed

```
Initial state: Admin A, Admin B (2 admins)

Request 1: A demotes B
Request 2: B demotes A

Without locking: both read admin count = 2, both proceed → zero admins.
```

### Deterministic locking strategy

`UpdateUserRoleAction` wraps the mutation in `DB::transaction()` and locks the **entire set of current Admin rows**:

1. `User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get()` — locks all Admin rows with deterministic order.
2. Reload the target user inside the same transaction.
3. If demoting an Admin: count from the locked admin set. If count ≤ 1 → 409.
4. Otherwise persist.

### How the race resolves

- Transaction 1 acquires locks on all Admin rows → sees count = 2 → demotes B → commits → releases locks.
- Transaction 2 acquires locks on all Admin rows (now only A is admin) → sees count = 1 → throws 409.

The `orderBy('id')` ensures deterministic lock acquisition order, reducing deadlock risk.

### Testing strategy

SQLite `:memory:` does not prove MySQL locking concurrency. The domain invariant is tested deterministically by verifying the business rule (single admin cannot be demoted → 409). The full Pest suite runs on MySQL 8.4 in CI (KAN-25 workflow). A true parallel transaction test is not planned for KAN-22 — the deterministic locking strategy is the architectural guarantee.

## 8. Same-Role Behavior

| Requested | Current | Result |
|-----------|---------|--------|
| `admin` | `admin` | 200 OK, no mutation |
| `affiliate` | `affiliate` | 200 OK, no mutation |

The Action detects same-role early and returns the target user without acquiring the admin-set lock or persisting.

## 9. Règles de sécurité

- Les routes admin sont protégées par `auth:sanctum` + `admin` middleware.
- Le `UserPolicy` utilise le paramètre `$actor` (pas `Auth::id()`) pour la vérification d'auto-demotion.
- L'`UpdateUserRoleAction` garantit l'invariant du dernier Admin dans une transaction avec verrouillage de l'ensemble des Admin.
- Le champ `role` n'est PAS dans `User::$fillable` — pas de risque d'assignation de masse.
- Les champs sensibles (password, remember_token) sont exclus par l'attribut `#[Hidden]` et par le whitelisting explicite dans `AdminUserResource`.
- Aucun contournement business — Admin ne peut pas accéder aux Offres/Campagnes d'un autre utilisateur.
- Aucune escalade de privilèges — l'inscription et le profil n'acceptent pas le champ `role`.
- L'Admin ne peut pas se dégrader (protection par Policy).
- Le dernier Admin ne peut pas être dégradé (protection par Action, transaction-safe avec verrouillage).

## 10. Pagination

- Taille par défaut: 15 par page
- Tri par: `id` ASC (ordre d'inscription)
- Utilise `paginate()` de Laravel — retourne `data`, `links`, `meta`

## 11. Recherche et filtres

- `?search=term` — recherche partielle `LIKE %term%` sur `name` et `email`. La sensibilité à la casse dépend de la collation configurée sur la base de données.
- `?role=affiliate` — filtre exact par valeur d'enum `UserRole`
- Les deux paramètres sont optionnels et combinables
- Aucun tri personnalisé — ordre fixe par `id ASC`

## 12. Ressource

### AdminUserResource

Champs exposés:
- `id` (integer)
- `name` (string)
- `email` (string)
- `role` (string — valeur de l'enum)
- `email_verified_at` (datetime|null)
- `created_at` (datetime)
- `updated_at` (datetime)

Champs JAMAIS exposés:
- `password`
- `remember_token`
- Token hashes (Sanctum)
