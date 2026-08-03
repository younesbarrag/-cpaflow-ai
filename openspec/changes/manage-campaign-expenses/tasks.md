# Tasks - KAN-17: Gérer les dépenses d'une campagne

All tasks are completed. This file records the implementation history.

**Note:** This file contains implementation tasks (checkboxes). Planned Pest test scenarios are documented separately in the specification and counted in the final report.

## 1. Domain/Schema

- [x] **T1.1** Create the additive `create_campaign_expenses_table` migration with the exact columns (`id`, `campaign_id`, `amount`, `spent_at`, `description`, `created_at`, `updated_at`), `campaign_id` cascade foreign key; verify by inspecting `php artisan migrate:status` without executing migrations.
- [x] **T1.2** Add migration-focused Pest assertions for cascade behavior (deleting Campaign removes Expenses), correct column types, no defaults on `amount` and `spent_at`, nullable `description`, and absence of `user_id`, `offer_id`, `category`, `type`, `source`, `reference`, `status`, `deleted_at`, and soft deletes; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=expense_schema`.

## 2. Model/Relationships

- [x] **T2.1** Create `app/Models/CampaignExpense.php` with fillable fields (`campaign_id`, `amount`, `spent_at`, `description`), casts (`amount` → `'decimal:2'`, `spent_at` → `'date'`), and `campaign()` BelongsTo relationship; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=expense_model`.
- [x] **T2.2** Add `Campaign::expenses()` HasMany relationship; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=campaign_expenses_relationship`.
- [x] **T2.3** Add `CampaignExpense::campaign()` inverse BelongsTo relationship; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=expense_inverse_relationship`.
- [x] **T2.4** Create `database/factories/CampaignExpenseFactory.php` with valid defaults (Campaign state, positive amount, past date, optional description); verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=expense_factory`.

## 3. Validation

- [x] **T3.1** Create `app/Http/Requests/Api/V1/CampaignExpense/StoreCampaignExpenseRequest.php` with `authorize()` that retrieves the route-bound Campaign and checks `CampaignPolicy::recordExpense`, and `rules()` validating `amount` as `required|numeric|gt:0|max:9999999999.99|decimal:0,2`, `spent_at` as `required|date|before_or_equal:today`, `description` as `nullable|string|max:10000`; campaign_id stripped in prepareForValidation; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=store_form_request`.
- [x] **T3.2** Create `app/Http/Requests/Api/V1/CampaignExpense/UpdateCampaignExpenseRequest.php` with `authorize()` and `rules()` supporting partial updates for `amount`, `spent_at`, and `description`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=update_form_request`.
- [x] **T3.3** Confirm that the Form Request rules do NOT accept `campaign_id` from the body; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=form_request_no_campaign_id`.

## 4. Authorization/Security

- [x] **T4.1** Add `recordExpense(User $user, Campaign $campaign): bool`, `viewExpenses(User $user, Campaign $campaign): bool`, `updateExpense(User $user, Campaign $campaign): bool`, `deleteExpense(User $user, Campaign $campaign): bool` methods to existing `app/Policies/CampaignPolicy.php` using the existing `ownsCampaign` private method; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=campaign_policy`.
- [x] **T4.2** Test guest `401`, owner access permitted, foreign Campaign `404` (nested-resource security), missing Campaign `404`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=authorization`.

## 5. Expense Actions

- [x] **T5.1** Create `app/Actions/CampaignExpense/RecordCampaignExpenseAction.php` with `execute(Campaign $campaign, float $amount, string $spentAt, ?string $description = null): CampaignExpense` that creates the Expense through `$campaign->expenses()->create()`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=records_expense`.
- [x] **T5.2** Create `app/Actions/CampaignExpense/UpdateCampaignExpenseAction.php` with `execute(CampaignExpense $expense, array $fields): CampaignExpense` that updates the Expense; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=updates_expense`.
- [x] **T5.3** Create `app/Actions/CampaignExpense/DeleteCampaignExpenseAction.php` with `execute(CampaignExpense $expense): void` that deletes the Expense; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=deletes_expense`.
- [x] **T5.4** Test that `amount` persists accurately as decimal; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=amount_persists_accurately`.
- [x] **T5.5** Test that `spent_at` is stored as the client-provided date; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=spent_at_stored_correctly`.
- [x] **T5.6** Test that `description` is stored when provided; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=description_stored`.
- [x] **T5.7** Test that `description` is null when not provided; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=description_null_when_omitted`.

## 6. Financial Integrity

- [x] **T6.1** Test that missing `amount` returns `422` with `errors.amount`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=missing_amount`.
- [x] **T6.2** Test that empty `amount` returns `422`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=empty_amount`.
- [x] **T6.3** Test that zero `amount` returns `422`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=zero_amount`.
- [x] **T6.4** Test that negative `amount` returns `422`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=negative_amount`.
- [x] **T6.5** Test that more than 2 decimal places returns `422`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=excessive_decimals`.
- [x] **T6.6** Test that DECIMAL(12,2) overflow returns `422`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=decimal_overflow`.
- [x] **T6.7** Test that missing `spent_at` returns `422` with `errors.spent_at`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=missing_spent_at`.
- [x] **T6.8** Test that future `spent_at` returns `422`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=future_spent_at`.

## 7. Controller/API

- [x] **T7.1** Create `app/Http/Controllers/Api/V1/CampaignExpenseController.php` with `store`, `index`, `update`, `destroy` methods using scoped nested resolution (`$campaign->expenses()->findOrFail()`); verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=controller_endpoints`.
- [x] **T7.2** Register authenticated routes `POST /campaigns/{campaign}/expenses`, `GET /campaigns/{campaign}/expenses`, `PATCH /campaigns/{campaign}/expenses/{expense}`, `DELETE /campaigns/{campaign}/expenses/{expense}` in `routes/api.php`; verify with `php artisan route:list --path=api/v1/campaigns/{campaign}/expenses`.

## 8. Resource/Response

- [x] **T8.1** Create `app/Http/Resources/Api/V1/CampaignExpenseResource.php` returning exactly `id`, `campaign_id`, `amount` (two-decimal string), `spent_at` (YYYY-MM-DD), `description`, `created_at` (ISO 8601), `updated_at` (ISO 8601); verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=expense_resource`.
- [x] **T8.2** Test that the response does not expose `user_id`, `offer_id`, or internal ownership fields; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=response_shape`.

## 9. Nested-Resource Security

- [x] **T9.1** Test owned Campaign + Expense from another owned Campaign → 404; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=cross_campaign_expense_mismatch`.
- [x] **T9.2** Test owned Campaign + Expense from foreign user's Campaign → 404; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=foreign_user_expense_mismatch`.
- [x] **T9.3** Test that no foreign Expense can be modified; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=no_foreign_expense_modified`.
- [x] **T9.4** Test that no foreign Expense can be deleted; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=no_foreign_expense_deleted`.

## 10. Relationships

- [x] **T10.1** Test `Campaign::expenses()` returns the correct Expenses; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=campaign_expenses_relationship`.
- [x] **T10.2** Test `CampaignExpense::campaign()` returns the correct Campaign; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=expense_inverse_relationship`.
- [x] **T10.3** Test cascade deletion: deleting a Campaign removes its Expenses; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=cascade_behavior`.

## 11. Update/Delete Behavior

- [x] **T11.1** Test update modifies only provided fields; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=update_partial`.
- [x] **T11.2** Test update with invalid amount returns `422`; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=update_invalid_amount`.
- [x] **T11.3** Test delete removes the expense from database; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=delete_removes_expense`.
- [x] **T11.4** Test wrong parent Campaign update → 404; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=wrong_parent_update`.
- [x] **T11.5** Test wrong parent Campaign delete → 404; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=wrong_parent_delete`.

## 12. Campaign Status Independence

- [x] **T12.1** Test expense can be recorded for a draft campaign; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=draft_campaign_expense`.
- [x] **T12.2** Test expense can be recorded for an active campaign; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=active_campaign_expense`.
- [x] **T12.3** Test expense can be recorded for a suspended campaign; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=suspended_campaign_expense`.

## 13. Budget Behavior

- [x] **T13.1** Test that cumulative expenses may exceed campaign budget; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=expenses_can_exceed_budget`.

## 14. Index/Pagination

- [x] **T14.1** Test owner can list expenses; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=owner_can_list`.
- [x] **T14.2** Test foreign Campaign list → 403; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=foreign_campaign_list`.
- [x] **T14.3** Test pagination is 15 per page; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=pagination_15`.
- [x] **T14.4** Test ordering is spent_at DESC, id DESC; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=ordering_spent_at_id_desc`.
- [x] **T14.5** Test expenses from other Campaigns excluded; verify with `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php --filter=other_campaigns_excluded`.

## 15. Regression

- [x] **T15.1** Test KAN-16 conversions remain unaffected; verify with `php artisan test tests/Feature/Api/V1/ConversionApiTest.php`.
- [x] **T15.2** Test KAN-14 tracking link generation remains unaffected; verify with `php artisan test tests/Feature/Api/V1/TrackingLinkGenerationApiTest.php`.
- [x] **T15.3** Test KAN-15 tracking redirect remains unaffected; verify with `php artisan test tests/Feature/TrackingRedirectTest.php`.
- [x] **T15.4** Test full regression suite: `php artisan test`.

## 16. Postman/Newman

- [x] **T16.1** Create `postman/CPAFlow-AI-KAN-17.postman_collection.json` (Collection v2.1) with tests for: health check, register owner, login, create offer, create campaign, record expense (201), list expenses (200), update expense (200), invalid expense (422), delete expense (204), unknown campaign (404), foreign campaign (403); verify with Newman run.
- [x] **T16.2** Verify Postman collection passes against local environment; do not force database state through debug endpoints — persistence behavior remains covered by Pest.

## 17. Documentation

- [x] **T17.1** Update only the final KAN-17 implementation in `docs/conception-technique.md`: update MLD for `campaign_expenses` table (confirm `campaign_id`, `amount`, `spent_at`, `description`), add implementation status, add routes to API routes list; verify with `git diff --check -- docs/conception-technique.md`.
- [x] **T17.2** Ensure documentation does not claim dashboard, analytics, profit, ROI, ROAS, budget enforcement, categories, or frontend functionality; verify with `git diff -- docs/conception-technique.md`.

## 18. Regression/Formatting

- [x] **T18.1** Run formatting verification without changing files: `vendor/bin/pint --test`.
- [x] **T18.2** Run the focused CampaignExpense suite: `php artisan test tests/Feature/Api/V1/CampaignExpenseApiTest.php`.
- [x] **T18.3** Run the full regression suite: `php artisan test`.
- [x] **T18.4** Inspect exact route methods, URIs, middleware, and names: `php artisan route:list --path=api/v1/campaigns/{campaign}/expenses`.
- [x] **T18.5** Inspect migration registration without executing: `php artisan migrate:status`.
- [x] **T18.6** Check whitespace and patch integrity: `git diff --check`.

## 19. Final Review

- [x] **T19.1** Review the final worktree and confirm only approved KAN-17 implementation/documentation files changed, with no dependency, lockfile, generated, staged, or unrelated changes: `git status --short` and `git diff --stat`.
- [x] **T19.2** Confirm no destructive database command, dependency installation, Jira update, staging, commit, or push occurred; record verification evidence in the implementation report.

## 20. Checkbox Count

**Total implementation checkboxes: 53. Completed: 53. Remaining: 0.**
