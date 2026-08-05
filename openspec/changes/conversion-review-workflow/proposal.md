# Proposal: Conversion Review Workflow

## Summary
Add a review workflow for conversions, allowing campaign owners to approve or reject pending conversions through explicit domain actions.

## Current State
- Conversions are created with `Pending` status via `POST /api/v1/campaigns/{campaign}/conversions`
- No HTTP mechanism exists to transition `Pending → Approved` or `Pending → Rejected`
- Dashboard counts all statuses for `conversion_count` but sums revenue only from `Approved` conversions
- This is a documented functional gap

## Proposed Solution
Add two explicit action endpoints under campaign scope:
```
POST /api/v1/campaigns/{campaign}/conversions/{conversion}/approve
POST /api/v1/campaigns/{campaign}/conversions/{conversion}/reject
```

## Authorization
- Owner-based: the authenticated user who owns the Campaign may review that Campaign's Conversions
- Ownership chain: `User → Offer → Campaign → Conversion`
- Admin privileges do NOT bypass business-resource ownership
- Same authorization pattern as campaign activate/suspend

## Success Criteria
1. Campaign owner can approve a Pending conversion (status → Approved)
2. Campaign owner can reject a Pending conversion (status → Rejected)
3. Same-state retries return 200 idempotent (no new write)
4. Opposite terminal transitions return 409 Conflict
5. Non-owners receive 403 Forbidden
6. Wrong-parent conversions return 404 Not Found
7. Revenue snapshot is preserved (no recalculation on review)
8. `converted_at` is preserved (review time is not recorded)
9. Dashboard revenue and profit update correctly after approval
