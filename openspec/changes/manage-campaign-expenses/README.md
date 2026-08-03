# OpenSpec — KAN-17: Gérer les dépenses d'une campagne

## Status

**Planning complete.** Awaiting implementation approval.

## Story

KAN-17 — Gérer / enregistrer les dépenses d'une campagne

## Branch

`feature/KAN-17-campaign-expenses`

## Summary

Add authenticated API endpoints to record, list, update, and delete campaign expenses. Each expense is a financial transaction linked to a Campaign, with amount, date, and optional description.

## Files

| File | Purpose |
|------|---------|
| `proposal.md` | Problem statement, objectives, scope, MVP interpretation |
| `design.md` | Data model, schema, endpoints, authorization, validation, actions, nested-resource security |
| `spec.md` | Functional requirements, behavioral scenarios, exclusions |
| `tasks.md` | Implementation tasks (53 checkboxes) |
| `README.md` | This file |

## Quick Start

```bash
# Review the plan
cat openspec/changes/manage-campaign-expenses/proposal.md

# Review the design
cat openspec/changes/manage-campaign-expenses/design.md

# Review the spec
cat openspec/changes/manage-campaign-expenses/spec.md

# Review tasks
cat openspec/changes/manage-campaign-expenses/tasks.md
```

## Key Decisions

1. **MVP Interpretation**: "Gérer les dépenses d'une campagne" = CRUD for individual campaign expense transactions.
2. **Schema**: Follows MLD — `campaign_expenses` with `amount`, `spent_at`, `description`.
3. **No budget enforcement**: `Campaign.budget` remains informational.
4. **No categories**: Minimal schema per MLD. Categories can be added later.
5. **Client-controlled `spent_at`**: Historical expense entry supported, future dates rejected.
6. **Full CRUD**: Create, list, update, delete — approved KAN-17 MVP scope.
7. **Hard delete**: No soft deletes, consistent with existing conventions.
8. **DECIMAL(12,2)**: Consistent with other money fields in the codebase.
9. **Nested-resource security**: Scoped resolution through `$campaign->expenses()->findOrFail()` prevents cross-Campaign Expense manipulation.
10. **Relationship naming**: `Campaign::expenses()` (idiomatic short name).

## Security Model

- Campaign-level ownership: `CampaignPolicy` via `Campaign → Offer → User`
- Child membership: Scoped nested resolution through `$campaign->expenses()->findOrFail()`
- Campaign/Expense mismatch → `404 Not Found`

## Known Issues

- Pre-existing flaky test: `CampaignWebTest::success_flash_renders_after_campaign_creation` (non-deterministic, unrelated to KAN-17).
