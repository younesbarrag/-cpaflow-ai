# KAN-22: Administration des utilisateurs

## Story

En tant qu'administrateur, je veux gérer les comptes utilisateurs (lister, consulter, modifier le rôle) afin de maintenir la gouvernance de la plateforme CPAFlow.

## Status

| Item | Status |
|------|--------|
| OpenSpec | Planning finalisé (révision 3) |
| Implémentation | Terminée |
| Tests | 30/30 PASS, 74 assertions |

## Files

| File | Status | Purpose |
|------|--------|---------|
| `openspec/changes/admin-user-management/proposal.md` | Final | Proposition |
| `openspec/changes/admin-user-management/design.md` | Final | Décisions de conception |
| `openspec/changes/admin-user-management/spec.md` | Final | Spécification |
| `openspec/changes/admin-user-management/tasks.md` | Final | Tâches d'implémentation |

## Quick Start

```bash
# Exécuter les tests
php artisan test --filter=AdminUserApiTest

# Exécuter Newman (scope limité — pas de test admin réussi)
npx newman run postman/CPAFlow-AI-KAN-22.postman_collection.json -e postman/CPAFlow-AI-Local.postman_environment.json
```

## Key Design Decisions

| Decision | Value |
|----------|-------|
| Admin middleware | `EnsureUserIsAdmin` (existant, pas de changement) |
| Authorization layers | Middleware (namespace) → Policy (self-demotion) → Action (last-admin invariant) |
| Self-demption | Interdit — `UserPolicy::updateRole($actor, $target)` (couche autorisation, `$actor->id !== $target->id`) |
| Last-admin | Protégé — `UpdateUserRoleAction` dans `DB::transaction` avec verrouillage de l'ensemble des Admin (`orderBy('id')->lockForUpdate()`) |
| Same-role | 200 OK, pas de mutation effective |
| No migration | Le schéma users a déjà toutes les colonnes nécessaires |
| User deletion | Exclu — cascade destructrice sur les relations business |
| Business bypass | Non — Admin ne contournent pas Offer/Campaign ownership |
| Pagination | 15 par page, ordre `id ASC` |
| Search | `?search=term` sur name/email (LIKE, case-insensitivity selon collation DB) |
| Role filter | `?role=admin` ou `?role=affiliate` |
| Resource | `AdminUserResource` — id, name, email, role, email_verified_at, created_at, updated_at |
| Sensitive fields | password, remember_token jamais exposés |
| Newman scope | Limité — pas de compte admin déterministe, Pest est autoritaire |
| Testing | Pest, RefreshDatabase, 570+ baseline |

## Authorization Architecture

```
Request → EnsureUserIsAdmin middleware (403 if non-admin)
       → UserPolicy::updateRole($actor, $target) (403 if self-demotion)
       → UpdateUserRoleRequest validation (422 if invalid role)
       → UpdateUserRoleAction:
           1. Same-role check → 200 (no-op)
           2. DB::transaction:
              a. Lock ALL Admin rows (orderBy('id'), lockForUpdate)
              b. Reload target
              c. If demoting Admin and count ≤ 1 → 409
              d. Persist role
```

## Concurrency-Safe Admin-Set Locking

```
T1: A demotes B
T2: B demotes A

T1 locks all Admin rows → sees 2 admins → demotes B → commits
T2 locks all Admin rows → sees 1 admin (A) → 409 Conflict
```

## Scope Exclusions

- User deletion (cascade through 8+ tables)
- Password reset by Admin
- Impersonation / login-as
- Session revocation UI
- Banning / account deactivation
- Email verification override
- Bulk user actions
- CSV export
- Admin analytics dashboard
- Audit trail
- User soft deletes
- GDPR deletion
- OAuth administration
- Role/permission packages (Spatie etc.)
- Blade frontend (API-only)
- Admin bypass to Offer/Campaign policies
- Deterministic admin seeding

## OpenSpec Checkbox Count

**Total: 28. Completed: 28. Remaining: 0.**
