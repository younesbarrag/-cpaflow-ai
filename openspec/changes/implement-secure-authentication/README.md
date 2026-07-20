# OpenSpec Change: implement-secure-authentication

## Overview

This OpenSpec change defines the implementation of secure user registration and login for the CPAFlow AI Laravel backend. It establishes Laravel Breeze Blade for web authentication and Laravel Sanctum for API Bearer Token authentication.

## Change Name

`implement-secure-authentication`

## Jira Ticket

KAN-9 — Permettre l'inscription et la connexion sécurisées

## Files

| File | Purpose |
|------|---------|
| `proposal.md` | Problem statement, objectives, scope, and dependencies |
| `design.md` | Technical decisions, architecture, and strategies |
| `tasks.md` | Ordered implementation tasks with verification steps |
| `spec.md` | Requirements, scenarios, and acceptance criteria |

## Quick Reference

### Target State

- Laravel Breeze Blade for web registration and login (`/register`, `/login`, `/logout`)
- Laravel Sanctum for API Bearer Token authentication (`/api/v1/auth/register`, `/api/v1/auth/login`)
- User model with mandatory `HasApiTokens` trait for Sanctum token operations
- Users table with `role` column (`string(20)`, default: `affiliate`, indexed)
- Shared `RegisterUserAction` with explicit trusted role assignment (not mass assignment)
- API login with explicit rate-limiting lifecycle in controller (5 attempts/min, clear on success)
- API registration with route-level throttle middleware (10 attempts/min per IP)
- Web login rate limiting handled by Breeze's `LoginRequest` (no duplication)
- Optional `device_name` input for API token creation
- Comprehensive Pest test suite with rate-limiter state reset between cases

### Key Decisions

1. **Breeze:** Install via Composer + `breeze:install blade` (legacy package, compatible with Laravel 13)
2. **Sanctum:** Manual Composer install (no `install:api` to preserve `routes/api.php`)
3. **HasApiTokens:** Mandatory on User model (required for `createToken()`)
4. **Registration Role:** Server-side only via `RegisterUserAction` explicit assignment, never exposed to input
5. **API Registration:** Returns 201 with user data (no token), separate login required
6. **API Login Rate Limiting:** Explicit lifecycle in controller (not route middleware): normalize email → build key → check attempts → increment on failure → clear on success
7. **Web Login Rate Limiting:** Breeze's `LoginRequest` handles internally (no additional middleware)
8. **API Registration Rate Limiting:** Route middleware `throttle:api-register` (10/min per IP)
9. **Token Contract:** `{ data: { user: {...} }, token: "...", token_type: "Bearer" }`
10. **Password Security:** `hashed` cast on User model, hidden attributes, minimum 8 characters
11. **Role Column:** `string('role', 20)` portable between MySQL and SQLite, indexed, default `affiliate`
12. **Role Assignment:** `new User()` + `fill()` + `$user->role = UserRole::Affiliate` (not `forceFill`/`forceCreate`)

### Exclusions

- Social login / OAuth
- Two-factor authentication
- Email verification customization
- Password reset (standard Breeze scaffolding only, no custom logic)
- Business features (offers, campaigns, etc.)
- Docker, CI/CD, deployment

## Validation

To validate this OpenSpec change:

1. Review all files in this directory
2. Verify tasks are ordered and verifiable
3. Check that scenarios cover all acceptance criteria
4. Ensure no scope creep beyond KAN-9

## Status

- [ ] Proposal reviewed
- [ ] Design approved
- [ ] Tasks verified
- [ ] Specification validated
- [ ] Ready for implementation
