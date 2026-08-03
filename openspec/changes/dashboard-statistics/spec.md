# Specification - KAN-18: Afficher les statistiques du dashboard

## 1. Business Rules

| # | Rule | Source | Status |
|---|------|--------|--------|
| R1 | Les données sont isolées par utilisateur authentifié — un Affiliate ne voit que ses propres offres, campagnes et statistiques. | conception-technique.md | Implémenté |
| R5 | Seules les conversions approuvées (approved) comptent dans le revenu. | conception-technique.md | **Ambiguïté bloquante** — aucune conversion n'est jamais approuvée actuellement |
| R6 | Les dépenses sont isolées par campagne et gérées en CRUD complet. | KAN-17 | Implémenté |

## 2. Metric Specifications

### 2.1 offer_count

| Property | Value |
|----------|-------|
| Formula | `COUNT(offers WHERE user_id = authenticated_user.id)` |
| Type | Integer |
| Zero value | `0` |
| Data source | `offers` table |
| Scope | Authenticated user's offers only |

### 2.2 campaign_count

| Property | Value |
|----------|-------|
| Formula | `COUNT(campaigns WHERE offer.user_id = authenticated_user.id)` |
| Type | Integer |
| Zero value | `0` |
| Data source | `campaigns` → `offers` join |
| Scope | Campaigns owned by authenticated user through Offer |

### 2.3 active_campaign_count

| Property | Value |
|----------|-------|
| Formula | `COUNT(campaigns WHERE offer.user_id = authenticated_user.id AND status = 'active')` |
| Type | Integer |
| Zero value | `0` |
| Data source | `campaigns` → `offers` join with status filter |
| Scope | Same as campaign_count, filtered to active status |

### 2.4 click_count

| Property | Value |
|----------|-------|
| Formula | `COUNT(tracking_clicks WHERE tracking_link.campaign.offer.user_id = authenticated_user.id)` |
| Type | Integer |
| Zero value | `0` |
| Data source | `tracking_clicks` → `tracking_links` → `campaigns` → `offers` join |
| Scope | Clicks on tracking links belonging to authenticated user's campaigns |

### 2.5 conversion_count

| Property | Value |
|----------|-------|
| Formula | `COUNT(conversions WHERE campaign.offer.user_id = authenticated_user.id [AND status IN (...)])` |
| Type | Integer |
| Zero value | `0` |
| Data source | `conversions` → `campaigns` → `offers` join |
| Status filter | **BLOCKING AMBIGUITY** — see proposal.md Section 8 |

### 2.6 revenue

| Property | Value |
|----------|-------|
| Formula | `SUM(conversions.revenue WHERE campaign.offer.user_id = authenticated_user.id [AND status IN (...)])` |
| Type | String (DECIMAL(12,2) serialized) |
| Zero value | `"0.00"` |
| Data source | `conversions.revenue` — server-snapshotted from `Offer.payout` |
| Precision | `number_format((float) $value, 2, '.', '')` |

### 2.7 total_expenses

| Property | Value |
|----------|-------|
| Formula | `SUM(campaign_expenses.amount WHERE campaign.offer.user_id = authenticated_user.id)` |
| Type | String (DECIMAL(12,2) serialized) |
| Zero value | `"0.00"` |
| Data source | `campaign_expenses.amount` — validated positive amounts |
| Precision | `number_format((float) $value, 2, '.', '')` |

### 2.8 profit

| Property | Value |
|----------|-------|
| Formula | `revenue − total_expenses` |
| Type | String (DECIMAL(12,2) serialized) |
| Zero value | `"0.00"` |
| Negative values | Supported — e.g., `"-250.00"` |
| Precision | `number_format((float) $value, 2, '.', '')` |

## 3. User Stories

### US-1: Authenticated user views dashboard statistics

**As** an authenticated affiliate
**I want** to see my aggregate performance metrics on the dashboard
**So that** I can evaluate my campaign performance at a glance

**Acceptance Criteria:**
- Given I am authenticated, when I visit `GET /dashboard`, then I see my real statistics.
- Given I have 3 offers and 2 campaigns, then `offer_count` = 3 and `campaign_count` = 2.
- Given I have 150 clicks across my campaigns, then `click_count` = 150.
- Given I have 10 conversions worth $250 total, then `conversion_count` = 10 and `revenue` = "250.00".
- Given my expenses total $180, then `total_expenses` = "180.00" and `profit` = "70.00".

### US-2: API consumer requests dashboard statistics

**As** an API client
**I want** to request aggregate statistics via `GET /api/v1/dashboard/statistics`
**So that** I can display performance data in external tools

**Acceptance Criteria:**
- Given I am authenticated with a Bearer token, when I request `GET /api/v1/dashboard/statistics`, then I receive `200` with `data.statistics` envelope.
- Given I have no data, then all metrics are `0` or `"0.00"`.
- Given I am a guest, then I receive `401`.

### US-3: Ownership isolation

**As** User A
**I want** to see only my own statistics
**So that** I cannot access another user's financial data

**Acceptance Criteria:**
- Given User A has 5 campaigns and User B has 10 campaigns, when User A requests statistics, then `campaign_count` = 5.
- Given User B has $1000 in revenue, when User A requests statistics, then `revenue` = "0.00".
- Given both users have clicks, conversions, and expenses, then each user sees only their own.

### US-4: Negative profit

**As** an affiliate
**I want** to see when my expenses exceed my revenue
**So that** I can make informed business decisions

**Acceptance Criteria:**
- Given revenue = "100.00" and total_expenses = "350.00", then `profit` = "-250.00".
- The negative sign is visible in the serialized response.

## 4. Explicit Exclusions

The following are NOT part of KAN-18:

- Period/date filters (KAN-19).
- Conversion approval/reject workflow.
- ROI/ROAS calculations.
- Conversion rate calculation.
- Charts or data visualization packages.
- Dashboard caching.
- Campaign budget enforcement.
- Unique visitor counting.
- Attribution analytics.
- AI features.
- Global UI redesign.

## 5. Test Scenarios

### S1: Empty-state statistics

| Step | Action | Expected |
|------|--------|----------|
| 1 | Register a new user | 201 |
| 2 | Login | 200, token received |
| 3 | Request `GET /api/v1/dashboard/statistics` | 200 |
| 4 | Verify all metrics | All zeros |

### S2: Statistics with data

| Step | Action | Expected |
|------|--------|----------|
| 1 | Login as existing user with data | 200 |
| 2 | Request `GET /api/v1/dashboard/statistics` | 200 |
| 3 | Verify `offer_count` matches user's actual offer count | Equal |
| 4 | Verify `campaign_count` matches user's actual campaign count | Equal |
| 5 | Verify `revenue` matches sum of user's conversion revenues | Equal |
| 6 | Verify `total_expenses` matches sum of user's expense amounts | Equal |
| 7 | Verify `profit` = `revenue` − `total_expenses` | Equal |

### S3: Ownership isolation

| Step | Action | Expected |
|------|--------|----------|
| 1 | Login as User A | 200 |
| 2 | Request statistics | 200 |
| 3 | Note User A's metrics | — |
| 4 | Login as User B (with different data) | 200 |
| 5 | Request statistics | 200 |
| 6 | Verify User B's metrics differ from User A's | Different |

### S4: Guest access

| Step | Action | Expected |
|------|--------|----------|
| 1 | Request `GET /api/v1/dashboard/statistics` without token | 401 |

### S5: Decimal precision

| Step | Action | Expected |
|------|--------|----------|
| 1 | Create conversions with revenue 0.10 and 0.20 | 201 each |
| 2 | Request statistics | 200 |
| 3 | Verify `revenue` = "0.30" | Exact string match |

### S6: Negative profit

| Step | Action | Expected |
|------|--------|----------|
| 1 | Create 1 conversion with revenue "10.00" | 201 |
| 2 | Create 1 expense with amount "50.00" | 201 |
| 3 | Request statistics | 200 |
| 4 | Verify `profit` = "-40.00" | Exact string match |

### S7: Campaign budget is not expense

| Step | Action | Expected |
|------|--------|----------|
| 1 | Create campaign with budget "1000.00" | 201 |
| 2 | Create no expenses | — |
| 3 | Request statistics | 200 |
| 4 | Verify `total_expenses` = "0.00" | Not "1000.00" |

## 6. Acceptance Criteria

- [ ] Guest request returns 401.
- [ ] Authenticated user receives statistics scoped to their data.
- [ ] Foreign user data is excluded from all metrics.
- [ ] Offer count matches actual count.
- [ ] Campaign count matches actual count.
- [ ] Active campaign count matches campaigns with status = active.
- [ ] Click count matches actual clicks through ownership chain.
- [ ] Conversion count is correct per decided status filter.
- [ ] Revenue sums trusted Conversion.revenue values.
- [ ] Total expenses sum CampaignExpense.amount.
- [ ] Profit = revenue − total_expenses.
- [ ] Negative profit is supported.
- [ ] Zero-data user receives all zeros.
- [ ] Financial totals maintain DECIMAL(12,2) precision.
- [ ] API response follows `data.statistics` envelope.
- [ ] Blade dashboard displays real statistics.
- [ ] No period filtering is implemented.
- [ ] Full Pest suite remains green.
