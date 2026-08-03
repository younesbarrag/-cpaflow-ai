# KAN-18: Afficher les statistiques du dashboard

## Summary

Extend the existing Blade dashboard with real aggregate statistics derived from the authenticated user's own data: clicks, conversions, revenue, expenses, and profit. Provide a new API endpoint that returns these statistics as JSON. KAN-18 establishes all-time baseline aggregates without period filtering — period/date filters belong to KAN-19.

## Status

**Implemented.** KAN-18 is fully implemented, tested, and verified. Ready for manual commit.

## Blocking Ambiguity

**Conversion Status Filter** — Business rule R5 says "only approved conversions count for revenue" but `RecordConversionAction` creates ALL conversions as `ConversionStatus::Pending`. No approval workflow exists. Requires human decision before implementation. See `proposal.md` Section 8 for options.

## Files

| File | Purpose |
|------|---------|
| `proposal.md` | Business case, scope, blocking ambiguity, success criteria |
| `design.md` | Architecture, query strategy, API endpoint design, test plan |
| `spec.md` | Full specification, metric definitions, user stories, test scenarios, exclusions |
| `tasks.md` | Implementation checkboxes (42 total, all pending) |
| `README.md` | This file |

## Key Decisions

- **Endpoint:** `GET /api/v1/dashboard/statistics` under `auth:sanctum`
- **Statistics scope:** All-time aggregate — no period filtering (KAN-19)
- **Architecture:** `GetDashboardStatisticsAction` shared between API and Blade
- **Query strategy:** 6 indexed database queries, SUM/COUNT with COALESCE
- **Decimal precision:** `DECIMAL(12,2)` for all financial totals
- **Profit formula:** `revenue − total_expenses`
- **No migration required** — aggregation queries only

## Metrics

| Metric | Type | Zero Value |
|--------|------|------------|
| `offer_count` | Integer | `0` |
| `campaign_count` | Integer | `0` |
| `active_campaign_count` | Integer | `0` |
| `click_count` | Integer | `0` |
| `conversion_count` | Integer | `0` |
| `revenue` | String (DECIMAL) | `"0.00"` |
| `total_expenses` | String (DECIMAL) | `"0.00"` |
| `profit` | String (DECIMAL) | `"0.00"` |

## Exclusions

- Period/date filters (KAN-19)
- Conversion approval/reject workflow
- ROI / ROAS calculations
- Conversion rate calculation
- Charts or data visualization packages
- Dashboard caching
- Campaign budget enforcement
- Unique visitor counting
- Attribution analytics
- AI features
- Global UI redesign

## Dependencies

- KAN-14 (Tracking Links) — click data
- KAN-15 (Tracking Clicks) — click counts
- KAN-16 (Conversions) — conversion counts and revenue
- KAN-17 (Campaign Expenses) — expense totals

## Related OpenSpec Changes

- `openspec/changes/manage-campaign-expenses/` — KAN-17
- `openspec/changes/record-conversion-without-duplicate/` — KAN-16
- `openspec/changes/create-tracking-link/` — KAN-14
- `openspec/changes/create-blade-demo-interface/` — KAN-31
