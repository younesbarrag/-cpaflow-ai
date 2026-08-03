# Specification - KAN-19: Filtrer les statistiques par période

## 1. Business Rules

| # | Rule | Source | Status |
|---|------|--------|--------|
| R1 | Les données sont isolées par utilisateur authentifié — un Affiliate ne voit que ses propres offres, campagnes et statistiques. | conception-technique.md | KAN-18 |
| R5 | Seules les conversions approuvées (approved) comptent dans le revenu. | conception-technique.md | KAN-18 |
| R6 | Les dépenses sont isolées par campagne et gérées en CRUD complet. | KAN-17 | KAN-18 |
| K19-R1 | Sans paramètre de période, l'endpoint retourne les agrégats all-time identiques à KAN-18. | KAN-19 | Approuvé |
| K19-R2 | Les métriques d'inventaire (offer_count, campaign_count, active_campaign_count) ne sont pas filtrées par période. | K19 | Approuvé |
| K19-R3 | Les métriques d'événements (click_count, conversion_count, revenue, total_expenses) sont filtrées par la date colonne autoritaire de chaque entité. | K19 | Approuvé |
| K19-R4 | Le profit est calculé comme revenu approuvé de la période moins dépenses de la période. | K19 | Approuvé |
| K19-R5 | Les frontières de date pour les colonnes TIMESTAMP utilisent un intervalle demi-ouvert [start, end). | K19 | Approuvé |
| K19-R6 | Les frontières de date pour les colonnes DATE (spent_at) utilisent un intervalle inclusif côté utilisateur, converti en demi-ouvert côté SQL. | K19 | Approuvé |
| K19-R7 | L'application utilise UTC comme timezone. "today" signifie aujourd'hui en UTC. | K19 | Approuvé |
| K19-R8 | Une période valide sans activité correspondante retourne des zéros pour les métriques d'événements, pas les métriques d'inventaire. | K19 | Approuvé |
| K19-R9 | Les valeurs financières restent des chaînes à deux décimales stables. | K19 | Approuvé |
| K19-R10 | L'isolation par propriété n'est pas affaiblie par les filtres de période. | K19 | Approuvé |
| K19-R11 | Les dates custom dans le futur sont rejetées (before_or_equal:today). | K19 | Approuvé |

## 2. Metric Specifications

### 2.1 offer_count

| Property | Value |
|----------|-------|
| Period filtered | **No** — all-time inventory metric |
| Formula | `COUNT(offers WHERE user_id = authenticated_user.id)` |
| Type | Integer |
| Zero value | `0` |
| Rationale | Offers are current inventory. Period-filtering would exclude legacy offers. |

### 2.2 campaign_count

| Property | Value |
|----------|-------|
| Period filtered | **No** — all-time inventory metric |
| Formula | `COUNT(campaigns WHERE offer.user_id = authenticated_user.id)` |
| Type | Integer |
| Zero value | `0` |
| Rationale | Campaigns are current inventory. Period-filtering would exclude legacy campaigns. |

### 2.3 active_campaign_count

| Property | Value |
|----------|-------|
| Period filtered | **No** — all-time status metric |
| Formula | `COUNT(campaigns WHERE offer.user_id = authenticated_user.id AND status = 'active')` |
| Type | Integer |
| Zero value | `0` |
| Rationale | Current schema stores only current status. No status history exists. |

### 2.4 click_count

| Property | Value |
|----------|-------|
| Period filtered | **Yes** |
| Date column | `tracking_clicks.created_at` |
| Column type | TIMESTAMP |
| Formula | `COUNT(tracking_clicks WHERE ... AND created_at >= start AND created_at < end)` |
| Type | Integer |
| Zero value | `0` |
| Rationale | `created_at` is the authoritative click timestamp per KAN-15 |

### 2.5 conversion_count

| Property | Value |
|----------|-------|
| Period filtered | **Yes** |
| Date column | `conversions.converted_at` |
| Column type | TIMESTAMP |
| Formula | `COUNT(conversions WHERE ... AND converted_at >= start AND converted_at < end)` |
| Type | Integer |
| Zero value | `0` |
| Status filter | All statuses (pending, approved, rejected) — same as KAN-18 |
| Rationale | `converted_at` is the server-generated business event timestamp per KAN-16 |

### 2.6 revenue

| Property | Value |
|----------|-------|
| Period filtered | **Yes** |
| Date column | `conversions.converted_at` |
| Column type | TIMESTAMP |
| Formula | `SUM(conversions.revenue WHERE ... AND status = 'approved' AND converted_at >= start AND converted_at < end)` |
| Type | String (DECIMAL(12,2) serialized) |
| Zero value | `"0.00"` |
| Precision | `number_format((float) $value, 2, '.', '')` |
| Status filter | Approved only — same as KAN-18 |

### 2.7 total_expenses

| Property | Value |
|----------|-------|
| Period filtered | **Yes** |
| Date column | `campaign_expenses.spent_at` |
| Column type | DATE |
| Formula | `SUM(campaign_expenses.amount WHERE ... AND spent_at >= from AND spent_at < toExclusive)` |
| Type | String (DECIMAL(12,2) serialized) |
| Zero value | `"0.00"` |
| Precision | `number_format((float) $value, 2, '.', '')` |

### 2.8 profit

| Property | Value |
|----------|-------|
| Period filtered | **Yes** (computed) |
| Formula | `period approved revenue − period total expenses` |
| Type | String (DECIMAL(12,2) serialized) |
| Zero value | `"0.00"` |
| Negative values | Supported |

## 3. User Stories

### US-1: Authenticated user filters dashboard statistics by predefined period

**As** an authenticated affiliate
**I want** to select a predefined period (today, last 7 days, last 30 days, this month) on the dashboard
**So that** I can see my performance metrics for a specific time range

**Acceptance Criteria:**
- Given I am authenticated, when I request `GET /api/v1/dashboard/statistics?period=last_7_days`, then I receive `200` with period-filtered metrics.
- Given I have clicks and conversions within the period, then `click_count` and `conversion_count` reflect only those within the period.
- Given I have approved conversions within the period, then `revenue` reflects only those within the period.
- Given I have expenses within the period, then `total_expenses` reflects only those within the period.
- Given I have no activity within the period, then event metrics are `0` or `"0.00"`.
- Given I request without parameters, then all-time KAN-18 values are returned.

### US-2: Authenticated user filters dashboard statistics by custom date range

**As** an authenticated affiliate
**I want** to specify a custom from/to date range
**So that** I can analyze any arbitrary time period

**Acceptance Criteria:**
- Given I request `GET /api/v1/dashboard/statistics?period=custom&from=2026-08-01&to=2026-08-03`, then I receive `200` with metrics filtered to Aug 1–3 inclusive.
- Given `from > to`, then I receive `422`.
- Given missing `from` or `to` with `period=custom`, then I receive `422`.
- Given `from` or `to` in the future, then I receive `422`.
- Given `from`/`to` without `period=custom`, then I receive `422`.

### US-3: Blade dashboard shows period filter

**As** an authenticated affiliate
**I want** a period selector on the Blade dashboard
**So that** I can filter dashboard statistics without using the API

**Acceptance Criteria:**
- Given I visit `/dashboard`, then I see a period selector with options: All time, Today, Last 7 days, Last 30 days, This month, Custom range.
- Given I select "Last 30 days", then the page reloads with `?period=last_30_days` and shows filtered metrics.
- Given I select "Custom range", then date inputs appear and I can specify from/to.
- Given the URL has `?period=last_30_days`, then the selector shows "Last 30 days" selected.
- Given inventory metrics are shown separately from filtered activity metrics.

### US-4: Backward compatibility

**As** an existing API consumer
**I want** the statistics endpoint to behave identically when no period parameters are provided
**So that** my existing integration is not broken

**Acceptance Criteria:**
- Given I request `GET /api/v1/dashboard/statistics` without parameters, then the response is identical to KAN-18.
- Given existing KAN-18 tests, then they pass without modification.

## 4. Date Column Strategy

| Metric | Date column | Column type | Why this column |
|---|---|---|---|
| `offer_count` | N/A | N/A | Not period-filtered (inventory) |
| `campaign_count` | N/A | N/A | Not period-filtered (inventory) |
| `active_campaign_count` | N/A | N/A | Not period-filtered (status, no history) |
| `click_count` | `tracking_clicks.created_at` | TIMESTAMP | Authoritative click time (KAN-15) |
| `conversion_count` | `conversions.converted_at` | TIMESTAMP | Business event time (KAN-16) |
| `revenue` | `conversions.converted_at` | TIMESTAMP | Revenue follows conversion date |
| `total_expenses` | `campaign_expenses.spent_at` | DATE | Client-controlled expense date |
| `profit` | (computed) | N/A | Period revenue − period expenses |

## 5. Boundary Semantics

### 5.1 TIMESTAMP columns (created_at, converted_at)

Half-open interval: `>= startOfDay(from) AND < startOfDay(to + 1 day)`

User-facing: inclusive on both ends (`from=2026-08-01&to=2026-08-03` includes Aug 1, 2, 3).

Internal SQL: `>= '2026-08-01 00:00:00' AND < '2026-08-04 00:00:00'`

### 5.2 DATE columns (spent_at)

Same half-open interval: `>= from AND < to + 1 day`

Since `spent_at` stores a date without time, `spent_at >= '2026-08-01' AND spent_at < '2026-08-04'` includes all rows where `spent_at` is Aug 1, 2, or 3.

### 5.3 Timezone

All date boundary calculations use UTC (`config('app.timezone')`). "today" means today in UTC. No timezone selector.

## 6. Explicit Exclusions

The following are NOT part of KAN-19:

- Conversion approval/reject workflow.
- Comparison with previous period.
- Percentage growth or trend analysis.
- Daily/weekly/monthly data series or chart buckets.
- Analytics export.
- ROI / ROAS calculations.
- Conversion rate calculation.
- Unique visitor counting.
- Timezone selector.
- Arbitrary owner filters.
- Frontend redesign or new JS/chart dependencies.
- Saved filters or presets.
- Scheduled reports.
- Schema changes or new migrations.
- Caching.

## 7. Test Scenarios

### S1: Backward compatibility

| Step | Action | Expected |
|------|--------|----------|
| 1 | Request `GET /api/v1/dashboard/statistics` without params | 200 |
| 2 | Verify response identical to KAN-18 all-time | Same structure and values |

### S2: Predefined period filtering

| Step | Action | Expected |
|------|--------|----------|
| 1 | Create data spread across multiple days | — |
| 2 | Request `?period=last_7_days` | 200 — filtered counts |
| 3 | Verify click_count includes only clicks within last 7 days | Equal |
| 4 | Verify revenue includes only approved conversions within last 7 days | Equal |

### S3: Custom range filtering

| Step | Action | Expected |
|------|--------|----------|
| 1 | Create data on 2026-08-01 and 2026-08-03 | — |
| 2 | Request `?period=custom&from=2026-08-01&to=2026-08-03` | 200 |
| 3 | Verify data from Aug 1 included | Counted |
| 4 | Verify data from Aug 3 included | Counted |

### S4: Boundary inclusion

| Step | Action | Expected |
|------|--------|----------|
| 1 | Create a click at exactly period start boundary | — |
| 2 | Create a click at end of last day in period | — |
| 3 | Request period covering both | 200 — both clicks counted |

### S5: Empty period

| Step | Action | Expected |
|------|--------|----------|
| 1 | Request period with no matching activity | 200 |
| 2 | Verify event metrics are 0 / "0.00" | Zeros |
| 3 | Verify inventory metrics remain at all-time values | Not zero if user has data |

### S6: Validation

| Step | Action | Expected |
|------|--------|----------|
| 1 | Request `?period=invalid` | 422 |
| 2 | Request `?period=custom` (no from/to) | 422 |
| 3 | Request `?period=custom&from=not-a-date` | 422 |
| 4 | Request `?period=custom&from=2026-08-03&to=2026-08-01` | 422 |
| 5 | Request `?period=custom&from=2099-01-01&to=2099-12-31` | 422 |

### S7: Ownership isolation with period

| Step | Action | Expected |
|------|--------|----------|
| 1 | User A and User B both have data in the same date range | — |
| 2 | User A requests `?period=last_30_days` | 200 |
| 3 | Verify User A sees only User A's data | Isolated |

### S8: Guest access

| Step | Action | Expected |
|------|--------|----------|
| 1 | Request `GET /api/v1/dashboard/statistics?period=today` without token | 401 |

## 8. Acceptance Criteria

- [ ] Guest request with period returns 401.
- [ ] No parameters → all-time KAN-18 values.
- [ ] Predefined periods return correctly filtered metrics.
- [ ] Custom range returns correctly filtered metrics.
- [ ] Invalid period → 422.
- [ ] Invalid date → 422.
- [ ] from > to → 422.
- [ ] Custom without from/to → 422.
- [ ] Future from/to → 422.
- [ ] from/to without custom period → 422.
- [ ] Empty period returns zero event metrics.
- [ ] Inventory counts remain all-time.
- [ ] Active campaign count remains all-time.
- [ ] Click count uses created_at.
- [ ] Conversion count uses converted_at.
- [ ] Revenue uses converted_at + approved only.
- [ ] Expenses use spent_at.
- [ ] Profit = period revenue − period expenses.
- [ ] Negative profit supported.
- [ ] Financial values remain two-decimal strings.
- [ ] Ownership isolation preserved with period filters.
- [ ] API response structure unchanged.
- [ ] Blade dashboard shows period selector.
- [ ] Blade URL-backed filter state.
- [ ] Query count bounded (≤ 10).
- [ ] Full Pest suite remains green.
- [ ] Postman/Newman collection validates period API.
