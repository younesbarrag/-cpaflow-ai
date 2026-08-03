# KAN-19: Filtrer les statistiques par période

## Summary

Extend the existing `GetDashboardStatisticsAction` to accept optional period/date-range query parameters on `GET /api/v1/dashboard/statistics` and the Blade `GET /dashboard` route. When no period is specified, return the same all-time KAN-18 aggregates — full backward compatibility. When a period is specified, apply date conditions inside SQL queries to event-based metrics (clicks, conversions, revenue, expenses) while keeping inventory counts (offers, campaigns, active campaigns) unchanged.

## Status

**Implemented.** KAN-19 is fully implemented, tested, and verified. Ready for manual commit.

## Story

KAN-19 — Filtrer les statistiques par période

## Branch

`feature/KAN-19-statistics-period-filters`

## Files

| File | Purpose |
|------|---------|
| `proposal.md` | Business case, objectives, scope, key decisions |
| `design.md` | Architecture, value object, query strategy, API design, Blade integration |
| `spec.md` | Full specification, metric definitions, user stories, test scenarios |
| `tasks.md` | Implementation checkboxes |
| `README.md` | This file |

## Key Decisions

1. **Backward compatibility:** No parameters = all-time KAN-18 result.
2. **Inventory counts not period-filtered:** `offer_count`, `campaign_count`, `active_campaign_count` remain all-time.
3. **Event metrics period-filtered:** `click_count`, `conversion_count`, `revenue`, `total_expenses` use date columns.
4. **Date columns:** `tracking_clicks.created_at`, `conversions.converted_at`, `campaign_expenses.spent_at`.
5. **Half-open boundaries:** `[start, end)` for all columns.
6. **Timezone:** UTC — uses `config('app.timezone')`.
7. **Architecture:** Extend existing Action — no duplicate.
8. **Value object:** `DashboardStatisticsPeriod` with `CarbonImmutable`.
9. **Form Request:** `DashboardStatisticsRequest` — shared API + Blade.
10. **No migration:** Existing indexes sufficient.
11. **Custom future dates rejected:** `before_or_equal:today`.
12. **No maximum range length.**

## Metrics

| Metric | Period filtered | Date column |
|--------|----------------|-------------|
| `offer_count` | No | N/A |
| `campaign_count` | No | N/A |
| `active_campaign_count` | No | N/A |
| `click_count` | Yes | `tracking_clicks.created_at` |
| `conversion_count` | Yes | `conversions.converted_at` |
| `revenue` | Yes | `conversions.converted_at` + approved |
| `total_expenses` | Yes | `campaign_expenses.spent_at` |
| `profit` | Yes | (computed) |

## Exclusions

- Approval/reject workflow
- Previous period comparison
- Percentage growth or trend analysis
- Daily/weekly/monthly data series or chart buckets
- Analytics export
- ROI / ROAS calculations
- Conversion rate calculation
- Unique visitors
- Timezone selector
- Arbitrary owner filters
- Frontend redesign
- New JS/chart dependencies
- Saved filters
- Scheduled reports
- Schema changes
- Caching

## Dependencies

- KAN-18 (Dashboard Statistics) — base Action and endpoint
- KAN-17 (Campaign Expenses) — `campaign_expenses.spent_at`
- KAN-16 (Conversions) — `conversions.converted_at`
- KAN-15 (Tracking Clicks) — `tracking_clicks.created_at`
