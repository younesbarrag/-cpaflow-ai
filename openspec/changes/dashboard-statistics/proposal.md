# Proposal - KAN-18: Afficher les statistiques du dashboard

## 1. Summary

Extend the existing Blade dashboard with real aggregate statistics derived from the authenticated user's own data: clicks, conversions, revenue, expenses, and profit. Provide a new API endpoint that returns these statistics as JSON. KAN-18 establishes all-time baseline aggregates without period filtering — period/date filters belong to KAN-19.

## 2. Problem

KAN-31 delivers a functional Blade dashboard showing only offer and campaign counts. Users have no visibility into performance metrics — clicks, conversions, revenue, expenses, or profit. KAN-14 through KAN-17 have produced the raw data (tracking clicks, conversions with revenue snapshots, campaign expenses) that now needs aggregation. Without dashboard statistics, users cannot evaluate campaign performance or financial health.

## 3. Objectives

- Display aggregate statistics on the existing Blade dashboard: offer count, campaign count, active campaign count, click count, conversion count, revenue, total expenses, and profit.
- Expose these statistics through a new authenticated API endpoint: `GET /api/v1/dashboard/statistics`.
- Scope all statistics to the authenticated user's data — no cross-user data leakage.
- Maintain decimal precision for all financial totals using database DECIMAL aggregates.
- Handle zero-data states gracefully (all values default to 0).
- Preserve the existing 402/402 full test suite.
- Design the statistics architecture so KAN-19 can later add period/date filtering without rewriting aggregation logic.

## 4. In Scope

- `GetDashboardStatisticsAction` — centralized statistics aggregation.
- `DashboardStatisticsController` — thin API controller.
- `DashboardStatisticsResource` — JSON serialization of statistics.
- `GET /api/v1/dashboard/statistics` route under `auth:sanctum`.
- Blade dashboard integration — replace placeholder counts with real statistics.
- Pest feature tests covering all statistics, authorization, empty states, and edge cases.
- Postman/Newman collection for the API endpoint.
- Technical documentation update.

## 5. Out of Scope

- Period/date filtering — KAN-19 owns this feature.
- Conversion approval/reject workflow — separate story.
- ROI / ROAS calculations — not yet required by repository evidence.
- Conversion rate calculation — not yet required by repository evidence.
- Charts or data visualization packages.
- Dashboard caching or performance optimization.
- Campaign budget enforcement.
- Revenue or expense editing beyond existing KAN-16/KAN-17 CRUD.
- Attribution analytics.
- Unique visitor counting.
- AI features.
- Global UI redesign.

## 6. Dependencies and Compatibility

- Existing `users`, `offers`, `campaigns`, `tracking_links`, `tracking_clicks`, `conversions`, `campaign_expenses` tables.
- Existing `Campaign` model with `offer()`, `conversions()`, `expenses()`, `trackingLinks()` relationships.
- Existing `Conversion` model with `revenue` DECIMAL(12,2) and `status` enum cast.
- Existing `CampaignExpense` model with `amount` DECIMAL(12,2).
- Existing `TrackingClick` model with `trackingLink()` BelongsTo.
- Existing `DashboardController` with Blade dashboard route.
- Existing ownership chain: `User → Offer → Campaign → {Conversions, Expenses, TrackingLinks → TrackingClicks}`.

## 7. Key Decisions

| Decision | Outcome |
|---|---|
| Endpoint location | `GET /api/v1/dashboard/statistics` under `auth:sanctum` |
| Route name | `api.v1.dashboard.statistics` |
| Statistics scope | All-time aggregate — no period filtering |
| Ownership chain | `User → Offer → Campaign → child records` |
| Revenue source | `SUM(conversions.revenue)` — trusted server-snapshotted values |
| Expense source | `SUM(campaign_expenses.amount)` — validated positive amounts |
| Profit formula | `revenue − total_expenses` |
| Conversion status filter | **BLOCKING AMBIGUITY** — see Section 8 |
| Decimal precision | `DECIMAL(12,2)` for all financial totals; serialized as `"1250.50"` |
| Zero-data behavior | All metrics default to `0` or `"0.00"` — no null values |
| Migration required | No — aggregation queries only, no new columns |
| New index required | Evaluate during implementation; additive migration if genuinely needed |

## 8. Conversion Status Decision (Resolved)

The blocking ambiguity has been resolved by explicit product decision:

### conversion_count

**Count ALL Conversion records** belonging to the authenticated user's Campaigns — pending, approved, and rejected. `conversion_count` represents the number of conversion records received/recorded, not the same metric as approved revenue.

### revenue

**SUM(conversions.revenue) ONLY where status = ConversionStatus::Approved.** Pending and rejected conversion revenue must NOT count. This preserves documented business rule R5: only approved conversions contribute to revenue.

### profit

**Approved revenue − actual campaign expenses.** Therefore: `profit = SUM(approved Conversion.revenue) − SUM(CampaignExpense.amount)`. Pending/rejected conversion revenue must not inflate profit. Profit may be negative. Do not clamp it to zero.

### Current approval workflow limitation

KAN-16 creates Conversions as Pending. There is currently no public approve/reject workflow. For normal HTTP flows, conversion_count can increase while revenue remains 0.00 until approved Conversion data exists. Approved Conversion scenarios are prepared deterministically in Pest using factories/database setup.

## 9. Success Criteria

- Guest request to `GET /api/v1/dashboard/statistics` returns `401`.
- Authenticated user receives statistics scoped to their own data only.
- Foreign user's campaigns, conversions, expenses, and clicks are excluded.
- Offer count matches the user's actual offer count.
- Campaign count matches the user's actual campaign count.
- Active campaign count matches campaigns with status = active.
- Click count matches total TrackingClicks through the ownership chain.
- Conversion count is correct according to the decided status filter.
- Revenue sums `Conversion.revenue` for included conversions.
- Total expenses sum `CampaignExpense.amount` across user's campaigns.
- Profit equals revenue minus total expenses.
- Negative profit is supported (expenses exceed revenue).
- Zero-data user receives all zeros.
- Financial totals maintain DECIMAL(12,2) precision.
- API response follows `data.statistics` envelope.
- Blade dashboard displays real statistics.
- Full Pest suite remains green.
- Postman/Newman collection validates the API endpoint.
- No period filtering is implemented (reserved for KAN-19).

## 10. Approval Gate

KAN-18 is fully implemented. All 42 tasks completed. 439/439 tests pass. Ready for manual commit.
