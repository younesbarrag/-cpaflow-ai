# OpenSpec Change: initialize-cpaflow-foundation

## Overview

This OpenSpec change defines the initialization of the CPAFlow AI Laravel backend foundation. It establishes the technical infrastructure required for API development, authentication, and business features.

## Change Name

`initialize-cpaflow-foundation`

## Jira Ticket

KAN-8 — Initialiser le projet Laravel et l'architecture API

## Files

| File | Purpose |
|------|---------|
| `proposal.md` | Problem statement, objectives, scope, and dependencies |
| `design.md` | Technical decisions, architecture, and strategies |
| `tasks.md` | Ordered implementation tasks with verification steps |
| `spec.md` | Requirements, scenarios, and acceptance criteria |

## Quick Reference

### Target State

- Laravel 13.19.0 with MySQL `cpaflow_ai` database
- API routes versioned under `/api/v1`
- Health endpoint: `GET /api/v1/health`
- Pest testing framework
- Laravel Pint code style compliance
- Initial architecture: `app/Actions`, `app/Services`, `app/Enums`

### Key Decisions

1. **API Routing:** Manual `routes/api.php` registration (no Sanctum)
2. **Health Endpoint:** Invokable controller, no business logic
3. **Testing:** Pest with feature tests
4. **Code Style:** Laravel Pint with default configuration
5. **Enums:** String-backed PHP 8.1+ enums

### Exclusions

- Authentication (KAN-9)
- Business features (later tickets)
- Infrastructure (Docker, CI/CD)

## Validation

To validate this OpenSpec change:

1. Review all files in this directory
2. Verify tasks are ordered and verifiable
3. Check that scenarios cover all acceptance criteria
4. Ensure no scope creep beyond KAN-8

## Status

- [ ] Proposal reviewed
- [ ] Design approved
- [ ] Tasks verified
- [ ] Specification validated
- [ ] Ready for implementation
