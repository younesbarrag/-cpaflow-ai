# Proposal - KAN-19: Filtrer les statistiques par période

## 1. Summary

Extend the existing `GetDashboardStatisticsAction` to accept optional period/date-range query parameters on `GET /api/v1/dashboard/statistics` and the Blade `GET /dashboard` route. When no period is specified, return the same all-time KAN-18 aggregates — full backward compatibility. When a period is specified, apply date conditions inside SQL queries to event-based metrics (clicks, conversions, revenue, expenses) while keeping inventory counts (offers, campaigns, active campaigns) unchanged.

## 2. Problem

KAN-18 delivers eight all-time aggregate metrics on the dashboard. Users cannot filter these metrics by time period — they cannot see "last 7 days" or "this month" performance. Without period filtering, the dashboard is less useful for evaluating recent campaign performance, making time-bound business decisions, or comparing activity across date ranges. KAN-18 explicitly deferred period filtering to KAN-19.

## 3. Objectives

- Add optional `period` and `from`/`to` query parameters to `GET /api/v1/dashboard/statistics`.
- Extend `GetDashboardStatisticsAction` to accept an optional period/range value object.
- Apply date conditions inside SQL — no PHP-side collection filtering.
- Keep the Action reusable by both the API controller and the Blade dashboard controller.
- Preserve all KAN-18 semantics: conversion_count = all statuses, revenue = approved only, profit = approved revenue − expenses.
- Preserve the existing 439/439 test baseline — existing tests remain valid.
- Add a compact period-filter UI to the Blade dashboard.
- Document exact date-column semantics for every metric.

## 4. In Scope

- `DashboardStatisticsRequest` — Form Request for query parameter validation.
- `DashboardStatisticsPeriod` — typed value object representing the resolved period boundaries.
- Extension of `GetDashboardStatisticsAction` to accept optional period constraints.
- Extension of `DashboardStatisticsController` to pass validated parameters to the Action.
- Extension of `DashboardController` to accept query parameters and pass period to the Action.
- Blade period-filter UI (compact selector, optional custom date inputs).
- Pest feature tests for all period-filter scenarios.
- Postman/Newman collection for period-filter API.
- Technical documentation update.

## 5. Out of Scope

- Conversion approval/reject workflow.
- Comparison with previous period.
- Percentage growth or trend analysis.
- Daily/weekly/monthly data series or chart buckets.
- Analytics export.
- ROI / ROAS calculations.
- Conversion rate calculation.
- Unique visitor counting.
- Timezone selector — application uses UTC.
- Arbitrary owner filters.
- Frontend redesign or new JS/chart dependencies.
- Saved filters or presets.
- Scheduled reports.
- Schema changes or new migrations (unless index genuinely required).
- Caching.

## 6. Dependencies and Compatibility

- Existing `GetDashboardStatisticsAction` — shared between API and Blade.
- Existing `DashboardStatisticsController` — thin controller, will pass validated params.
- Existing `DashboardStatisticsResource` — JSON serialization.
- Existing `DashboardController` — Blade controller, will accept query params.
- Existing `dashboard.blade.php` — will add compact filter UI.
- Existing migration indexes — sufficient for current scale.
- `config/app.php` timezone: `UTC`.

## 7. Key Decisions

| Decision | Outcome |
|---|---|
| Filter API | `period` enum + optional `from`/`to` for custom range |
| Default behavior | No parameters → all-time (KAN-18 backward-compatible) |
| Endpoint location | Same `GET /api/v1/dashboard/statistics` — no new route |
| Blade route | Same `GET /dashboard` — accepts query parameters |
| Action architecture | Extend existing `GetDashboardStatisticsAction` — no duplicate |
| Value object | `DashboardStatisticsPeriod` — resolved start/end boundaries |
| Form Request | `DashboardStatisticsRequest` — validates period/from/to |
| Date boundary strategy | Half-open for timestamps, inclusive for DATE columns |
| Timezone | UTC — application timezone, no selector |
| Inventory counts | `offer_count`, `campaign_count`, `active_campaign_count` NOT period-filtered |
| Event metrics | `click_count`, `conversion_count`, `revenue`, `total_expenses` period-filtered |
| Profit | Period revenue − period expenses |
| Migration | No schema change required |
| Financial precision | `number_format((float) $value, 2, '.', '')` preserved |
| Custom future dates | Rejected via `before_or_equal:today` |
| Maximum custom range | None |

## 8. Approval Gate

KAN-19 is fully approved. All ambiguities resolved. Ready for implementation.
