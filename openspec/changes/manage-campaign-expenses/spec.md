# Specification - KAN-17: Gérer les dépenses d'une campagne

## 1. Functional Requirements

### R1 - Expense Recording

| ID | Requirement |
|---|---|
| R1.1 | `POST /api/v1/campaigns/{campaign}/expenses` creates an Expense. |
| R1.2 | The route is authenticated and requires `auth:sanctum`. |
| R1.3 | The route is registered in `routes/api.php` under `/api/v1`. |
| R1.4 | The route name is `api.v1.campaigns.expenses.store`. |
| R1.5 | A valid request creates exactly one CampaignExpense row. |
| R1.6 | The Expense is linked to the route-bound Campaign. |
| R1.7 | Success returns `201 Created` with `data.campaign_expense`. |

### R2 - Expense Listing

| ID | Requirement |
|---|---|
| R2.1 | `GET /api/v1/campaigns/{campaign}/expenses` lists Expenses for a Campaign. |
| R2.2 | The route is authenticated and requires `auth:sanctum`. |
| R2.3 | The route name is `api.v1.campaigns.expenses.index`. |
| R2.4 | Returns paginated expenses for the Campaign, ordered by `spent_at DESC`, then `id DESC`. |
| R2.5 | Pagination is 15 records per page. |
| R2.6 | Success returns `200 OK` with paginated `data.campaign_expenses`. |

### R3 - Expense Update

| ID | Requirement |
|---|---|
| R3.1 | `PATCH /api/v1/campaigns/{campaign}/expenses/{expense}` updates an Expense. |
| R3.2 | The route is authenticated and requires `auth:sanctum`. |
| R3.3 | The route name is `api.v1.campaigns.expenses.update`. |
| R3.4 | Only provided fields are updated (partial update). |
| R3.5 | The Expense must belong to the route-bound Campaign (scoped resolution). |
| R3.6 | Success returns `200 OK` with `data.campaign_expense`. |

### R4 - Expense Deletion

| ID | Requirement |
|---|---|
| R4.1 | `DELETE /api/v1/campaigns/{campaign}/expenses/{expense}` deletes an Expense. |
| R4.2 | The route is authenticated and requires `auth:sanctum`. |
| R4.3 | The route name is `api.v1.campaigns.expenses.destroy`. |
| R4.4 | The Expense must belong to the route-bound Campaign (scoped resolution). |
| R4.5 | Success returns `204 No Content`. |
| R4.6 | Deleting an expense removes it from the database (hard delete). |

### R5 - Amount Validation

| ID | Requirement |
|---|---|
| R5.1 | `amount` is required. |
| R5.2 | `amount` must be numeric. |
| R5.3 | `amount` must be strictly greater than 0. |
| R5.4 | `amount` maximum is 9999999999.99 (fits DECIMAL(12,2)). |
| R5.5 | `amount` supports maximum 2 decimal places. |
| R5.6 | Missing or invalid `amount` returns `422` with `errors.amount`. |
| R5.7 | Zero amount is rejected. |
| R5.8 | Negative amount is rejected. |
| R5.9 | More than 2 decimal places is rejected. |
| R5.10 | Values exceeding DECIMAL(12,2) range are rejected. |

### R6 - Date Validation

| ID | Requirement |
|---|---|
| R6.1 | `spent_at` is required. |
| R6.2 | `spent_at` must be a valid date. |
| R6.3 | `spent_at` must be today or in the past. |
| R6.4 | Future dates are rejected. |
| R6.5 | Missing or invalid `spent_at` returns `422` with `errors.spent_at`. |
| R6.6 | Historical expense entry is supported (past dates are allowed). |

### R7 - Description Validation

| ID | Requirement |
|---|---|
| R7.1 | `description` is optional and nullable. |
| R7.2 | `description` is a string with max 10000 characters when provided. |
| R7.3 | Missing `description` stores null. |

### R8 - Ownership and Authorization

| ID | Requirement |
|---|---|
| R8.1 | The authenticated user must own the Campaign through `Campaign → Offer → User`. |
| R8.2 | Foreign Campaign returns `403 Forbidden`. |
| R8.3 | Guest request returns `401 Unauthorized`. |
| R8.4 | Unknown Campaign returns `404 Not Found`. |
| R8.5 | `campaign_id` cannot be forged through the request body. |

### R9 - Nested-Resource Security

| ID | Requirement |
|---|---|
| R9.1 | Expense must belong to the route-bound Campaign. |
| R9.2 | Campaign/Expense mismatch returns `404 Not Found`. |
| R9.3 | Scoped resolution through `$campaign->expenses()->findOrFail()` is mandatory. |
| R9.4 | Owned Campaign + Expense from another owned Campaign → 404. |
| R9.5 | Owned Campaign + Expense from foreign user's Campaign → 404. |

### R10 - Response Shape (Create/Update)

| ID | Requirement |
|---|---|
| R10.1 | Success response follows the `data.campaign_expense` envelope. |
| R10.2 | Response contains `id`, `campaign_id`, `amount`, `spent_at`, `description`, `created_at`, `updated_at`. |
| R10.3 | `amount` is serialized as a two-decimal string. |
| R10.4 | `spent_at` is serialized as a date string (YYYY-MM-DD). |
| R10.5 | `description` is null when not provided. |
| R10.6 | Timestamps are serialized as ISO 8601 strings. |

### R11 - Response Shape (List)

| ID | Requirement |
|---|---|
| R11.1 | List response returns paginated CampaignExpense resources. |
| R11.2 | Each expense in the array follows the same shape as R10. |
| R11.3 | Expenses are ordered by `spent_at DESC`, then `id DESC`. |
| R11.4 | Pagination is 15 records per page. |

### R12 - Relationships

| ID | Requirement |
|---|---|
| R12.1 | Campaign has many Expenses (`Campaign::expenses()`). |
| R12.2 | Expense belongs to one Campaign (`CampaignExpense::campaign()`). |
| R12.3 | Deleting a Campaign cascades to its Expenses. |

### R13 - Campaign Status Independence

| ID | Requirement |
|---|---|
| R13.1 | Expenses can be recorded for campaigns in any status (draft, active, suspended). |
| R13.2 | No campaign status check is performed during expense recording. |

### R14 - Financial Integrity

| ID | Requirement |
|---|---|
| R14.1 | `amount` is stored as `DECIMAL(12,2)` — no floating point. |
| R14.2 | `amount` has no database default — application supplies trusted value. |
| R14.3 | `spent_at` has no database default — application supplies client value. |
| R14.4 | Cumulative expenses may exceed `Campaign.budget` (budget is informational). |

## 2. Behavioral Scenarios

### S1 - Valid expense is recorded

**Given** an authenticated user owns a Campaign
**When** the user submits `POST /api/v1/campaigns/{id}/expenses` with valid `amount` and `spent_at`
**Then** one CampaignExpense is persisted with the correct `campaign_id`, `amount`, `spent_at`, and `description`.

### S2 - Success response has correct shape

**Given** a valid expense request
**When** the expense is recorded
**Then** the response is `201` with `data.campaign_expense` containing `id`, `campaign_id`, `amount`, `spent_at`, `description`, `created_at`, `updated_at`.

### S3 - Missing amount returns 422

**Given** an authenticated user owns a Campaign
**When** the user submits a request without `amount`
**Then** the response is `422` with `errors.amount`.

### S4 - Invalid amount returns 422

**Given** an authenticated user owns a Campaign
**When** the user submits `amount = 0`, `amount = -5`, or `amount = 12.345`
**Then** the response is `422` with `errors.amount`.

### S5 - Future date returns 422

**Given** an authenticated user owns a Campaign
**When** the user submits `spent_at` as a future date
**Then** the response is `422` with `errors.spent_at`.

### S6 - Foreign Campaign returns 403

**Given** an authenticated user does NOT own the Campaign
**When** the user submits a valid expense request
**Then** the response is `403 Forbidden`.

### S7 - Guest returns 401

**Given** no authenticated user
**When** a request is made to create an expense
**Then** the response is `401 Unauthorized`.

### S8 - Unknown Campaign returns 404

**Given** an authenticated user
**When** the Campaign does not exist
**Then** the response is `404 Not Found`.

### S9 - List expenses for a Campaign

**Given** an authenticated user owns a Campaign with expenses
**When** the user requests `GET /api/v1/campaigns/{id}/expenses`
**Then** the response is `200` with paginated expenses ordered by `spent_at DESC`, `id DESC`.

### S10 - Update expense

**Given** an authenticated user owns a Campaign with an expense
**When** the user submits `PATCH /api/v1/campaigns/{id}/expenses/{expense}` with new `amount`
**Then** the expense is updated and the response is `200` with the updated resource.

### S11 - Delete expense

**Given** an authenticated user owns a Campaign with an expense
**When** the user submits `DELETE /api/v1/campaigns/{id}/expenses/{expense}`
**Then** the expense is removed and the response is `204`.

### S12 - Cascade deletion

**Given** a Campaign with expenses
**When** the Campaign is deleted
**Then** all associated expenses are removed from the database.

### S13 - Cross-Campaign Expense ID mismatch

**Given** an authenticated user owns Campaign A and Campaign B
**When** the user requests `PATCH /campaigns/A/expenses/{expense_from_B}`
**Then** the response is `404 Not Found` and Expense from B is not modified.

### S14 - Expenses may exceed budget

**Given** an authenticated user owns a Campaign with budget 100
**When** the user records an expense of 150
**Then** the expense is created successfully (budget is informational).

## 3. Scenarios NOT in Scope

- Dashboard statistics.
- Profit calculation.
- ROI / ROAS.
- Revenue aggregation.
- Period filters.
- Budget enforcement.
- Expense categories/types.
- Recurring expenses.
- Attachments/receipts.
- Multi-currency.
- Tax calculations.
- Frontend expenses UI.
- Expense analytics.
- Budget alerts/notifications.
- Expense import.
- Batch operations.
- AI features.
- Filtering/search/period parameters.

## 4. Known Flaky Test

The following test is a known pre-existing flaky test (not caused by KAN-17):

```
tests/Feature/Web/CampaignWebTest.php
success_flash_renders_after_campaign_creation
```

Root cause: `OfferFactory` randomly selects `OfferStatus` including `Archived`, while the test assumes a usable Offer. This is non-deterministic and unrelated to KAN-17. KAN-17 must not be blamed for this failure.
