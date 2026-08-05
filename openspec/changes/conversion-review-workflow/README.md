# Conversion Review Workflow

## Overview
This change adds a review workflow for conversions, allowing campaign owners to approve or reject pending conversions through explicit domain actions with concurrency safety.

## Status
**Complete** — Verified with real Newman E2E runs

## OpenSpec Files
- [proposal.md](proposal.md) — Problem statement and proposed solution
- [design.md](design.md) — Technical architecture decisions
- [spec.md](spec.md) — API contract and behavior specifications
- [tasks.md](tasks.md) — Implementation checklist (66/66 tasks)

## Key Decisions
1. **Endpoints:** Explicit POST per action (`/approve`, `/reject`)
2. **Authorization:** Owner-based via CampaignPolicy (no Admin bypass)
3. **Action:** Single `ReviewConversionAction` with target status parameter
4. **Concurrency:** DB::transaction + lockForUpdate on conversion row
5. **State Transitions:** Pending → Approved/Rejected only; terminal states immutable
6. **Idempotency:** Same-state = 200 no-op; opposite terminal = 409 Conflict
7. **Revenue Snapshot:** Never recalculated on review
8. **converted_at:** Never changed on review
9. **Migration:** None required (status column already exists)

## Dependencies
- ConversionStatus enum — Already implemented (Pending/Approved/Rejected)
- CampaignPolicy — Already implemented (needs approveConversion/rejectConversion)
- ConversionResource — Already implemented (no change needed)

## Estimated Effort
- 66 implementation tasks across 15 phases
- 0 database migrations
- 46 test scenarios (security, approve, reject, conflicts, revenue, dashboard, period, concurrency, regression)
- 1 Newman collection

## Files to Create/Modify
| File | Action |
|------|--------|
| `app/Exceptions/InvalidConversionTransition.php` | CREATE |
| `app/Actions/Conversion/ReviewConversionAction.php` | CREATE |
| `app/Policies/CampaignPolicy.php` | MODIFY |
| `app/Http/Controllers/Api/V1/ConversionController.php` | MODIFY |
| `bootstrap/app.php` | MODIFY |
| `routes/api.php` | MODIFY |
| `tests/Feature/Api/V1/ConversionApiTest.php` | MODIFY |
| `docs/conception-technique.md` | MODIFY |
| `postman/CPAFlow-AI-CONVERSION-REVIEW.postman_collection.json` | CREATE |
