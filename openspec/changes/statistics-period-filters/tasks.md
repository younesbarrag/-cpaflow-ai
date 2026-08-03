# Tasks - KAN-19: Filtrer les statistiques par période

All tasks are completed. This file records the implementation history.

**Note:** This file contains implementation tasks (checkboxes). Planned Pest test scenarios are documented separately in the specification and counted in the final report.

## 1. Value Object

- [x] **T1.1** Create `app/DTOs/DashboardStatisticsPeriod.php` as a `final readonly` value object with private constructor, `?CarbonImmutable $start`, `?CarbonImmutable $endExclusive`, `?string $selectedPeriod` properties, static factory methods (`allTime()`, `today()`, `last7Days()`, `last30Days()`, `thisMonth()`, `custom(CarbonImmutable, CarbonImmutable)`), `fromRequest(Request)` factory, and `isAllTime(): bool` method.

## 2. Form Request

- [x] **T2.1** Create `app/Http/Requests/Api/V1/Dashboard/DashboardStatisticsRequest.php` with `authorize()` returning true, `rules()` validating `period` as nullable in allowed values, `from`/`to` as required with custom / prohibited otherwise, with date_format, before_or_equal:today, and comparison rules.

## 3. Action Extension

- [x] **T3.1** Add `?DashboardStatisticsPeriod $period = null` parameter to `GetDashboardStatisticsAction::execute()`.
- [x] **T3.2** Add `->when()` date conditions to click_count, conversion_count, approved revenue, and total_expenses queries.
- [x] **T3.3** Leave offer_count, campaign_count, active_campaign_count unfiltered.

## 4. API Controller Extension

- [x] **T4.1** Update `DashboardStatisticsController::show()` to use `DashboardStatisticsRequest` and `DashboardStatisticsPeriod::fromRequest()`.

## 5. Blade Controller Extension

- [x] **T5.1** Update `DashboardController::index()` to accept query params, use `DashboardStatisticsRequest`, resolve `DashboardStatisticsPeriod`, pass to Action and view.

## 6. Blade View Update

- [x] **T6.1** Add compact period selector to `resources/views/dashboard.blade.php` with select, conditional date inputs, and inventory/activity metric separation.

## 7. Tests

- [x] **T7.1** Create `tests/Feature/Api/V1/DashboardStatisticsPeriodFilterApiTest.php` with full coverage (51 tests, 109 assertions).

## 8. Postman/Newman

- [x] **T8.1** Create `postman/CPAFlow-AI-KAN-19.postman_collection.json`.

## 9. Documentation

- [x] **T9.1** Update `docs/conception-technique.md` with period filter documentation.

## 10. Verification

- [x] **T10.1** Run `vendor/bin/pint --test` — PASS.
- [x] **T10.2** Run full test suite `php artisan test` — 490/490 PASS.
- [x] **T10.3** Run `git diff --check` — PASS.

## 11. Checkbox Count

**Total implementation checkboxes: 12. Completed: 12. Remaining: 0.**
