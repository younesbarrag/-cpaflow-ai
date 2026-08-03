# Proposal - KAN-17: Gérer les dépenses d'une campagne

## 1. Summary

Add authenticated API endpoints to record, list, update, and delete campaign expenses. Each expense is a financial transaction linked to a Campaign, with amount, date, and optional description. Ownership is derived through `Campaign → Offer → User`.

## 2. Problem

KAN-14 generates tracking links, KAN-15 records clicks, and KAN-16 records conversions, but there is no mechanism to track campaign expenses (traffic costs, ad spend, etc.). CPAFlow needs to record individual expense transactions per campaign to enable future profit/ROI calculations. The MCD already defines `DEPENSE_CAMPAGNE` and the MLD defines `campaign_expenses` as planned.

## 3. Objectives

- Record an expense linked to a Campaign via an authenticated API endpoint.
- List all expenses for a Campaign with pagination.
- Update expense amount, description, and date.
- Delete an expense.
- Validate financial inputs carefully (positive amounts, no future dates).
- Verify ownership through `Campaign → Offer → User`.
- Guarantee child Expense belongs to parent Campaign (prevent cross-Campaign ID manipulation).
- Cover behavior with Pest feature tests and a Postman/Newman collection.

## 4. In Scope

- Additive `campaign_expenses` migration.
- `CampaignExpense` model.
- `Campaign::expenses()` and `CampaignExpense::campaign()` relationships.
- `RecordCampaignExpenseAction` for expense persistence.
- `UpdateCampaignExpenseAction` for expense modification.
- `DeleteCampaignExpenseAction` for expense deletion.
- `CampaignExpenseController` for CRUD endpoints.
- `StoreCampaignExpenseRequest` for create validation.
- `UpdateCampaignExpenseRequest` for update validation.
- `CampaignExpenseResource` for API serialization.
- Scoped nested resolution to prevent cross-Campaign Expense manipulation.
- Pest feature tests.
- Postman/Newman collection.
- Documentation update.

## 5. Out of Scope

- Dashboard statistics.
- Profit calculation.
- ROI / ROAS.
- Revenue aggregation.
- Period filters.
- Budget enforcement (Campaign.budget remains informational).
- AI features.
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
- Filtering/search/period parameters.

## 6. Approved MVP Interpretation

"Gérer les dépenses d'une campagne" is implemented as CRUD for individual campaign expense transactions. This is the approved KAN-17 MVP interpretation. The four operations (Create, List, Update, Delete) are the definitive scope. No additional operations are planned.
