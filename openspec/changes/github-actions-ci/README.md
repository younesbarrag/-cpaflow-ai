# KAN-25: Ajouter une intégration continue avec GitHub Actions

## Story

En tant qu'équipe, nous voulons mettre en place un pipeline CI avec GitHub Actions qui valide automatiquement chaque Pull Request avant merge, afin de garantir que les tests, le style de code et le build frontend restent toujours conformes.

## Status

| Item | Status |
|------|--------|
| OpenSpec | Planning finalisé |
| Implémentation | Terminée (workflow créé, docs mises à jour) |
| Tests locaux | En cours |
| Vérification GitHub | En attente — real GitHub Actions verification pending |

## Files

| File | Status | Purpose |
|------|--------|---------|
| `openspec/changes/github-actions-ci/proposal.md` | Created | Proposition |
| `openspec/changes/github-actions-ci/design.md` | Created | Décisions de conception |
| `openspec/changes/github-actions-ci/spec.md` | Created | Spécification |
| `openspec/changes/github-actions-ci/tasks.md` | Updated | Tâches d'implémentation |

## Quick Start

```bash
# Implémenter le workflow sur la branche feature
# Créer .github/workflows/ci.yml

# Pousser et créer une PR vers main
git push origin feature/KAN-25-github-actions-ci

# Les checks CI s'exécutent automatiquement sur la PR
# Attendre le vert avant merge
```

## KAN-25/KAN-26 Boundary

- **KAN-25:** CI uniquement — validation des tests, style et build sur PR
- **KAN-26:** CD — déploiement Azure, pipelines de release

## Key Design Decisions

| Decision | Value |
|----------|-------|
| CI Platform | GitHub Actions |
| PHP Version | 8.4 (single version, matching local) |
| Node Version | 24 (matching local) |
| Local Test DB | SQLite :memory: (phpunit.xml default) |
| CI Test DB | MySQL 8.4 (GitHub Actions service container) |
| Job Separation | 2 jobs: `backend-tests` + `frontend-build` |
| Backend Checks | composer validate --strict, composer install, migrate, pest tests, pint --test |
| Frontend Checks | npm ci, npm run build |
| AI/Prism Safety | Prism::fake in all tests — zero real AI calls, zero secrets |
| Queue in CI | sync |
| Cache in CI | array |
| Session in CI | array |
| Secrets Required | Zero |
| Concurrency | cancel-in-progress per ref |
| Composer Cache | None (fresh install from lockfile) |
| npm Cache | setup-node built-in |
| Permissions | contents: read only |

## OpenSpec Checkbox Count

**Total: 37. Completed: 31. Remaining: 6.**

Remaining tasks require GitHub execution (push/PR + real CI run) or final report production.
