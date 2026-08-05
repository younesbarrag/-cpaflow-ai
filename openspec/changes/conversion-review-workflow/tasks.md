# Tasks: Conversion Review Workflow

**Status:** Complete
**Progress:** 66/66

---

## Phase 1: Exception & Policy (3 tasks)

- [x] 1.1 Create `app/Exceptions/InvalidConversionTransition.php`
  - Extend `DomainException`
  - Add `$from` and `$to` ConversionStatus properties
  - Constructor message: "Conversion cannot transition from {$from->value} to {$to->value}."

- [x] 1.2 Add `approveConversion` method to `app/Policies/CampaignPolicy.php`
  - Reuse `ownsCampaign()` check
  - Signature: `approveConversion(User $user, Campaign $campaign): bool`

- [x] 1.3 Add `rejectConversion` method to `app/Policies/CampaignPolicy.php`
  - Reuse `ownsCampaign()` check
  - Signature: `rejectConversion(User $user, Campaign $campaign): bool`

---

## Phase 2: Domain Action (3 tasks)

- [x] 2.1 Create `app/Actions/Conversion/ReviewConversionAction.php`
  - Accept Campaign, conversion ID, target ConversionStatus
  - Open DB::transaction
  - Resolve conversion through `$campaign->conversions()->whereKey($conversionId)->lockForUpdate()->firstOrFail()`
  - If current === target: return existing (idempotent no-op)
  - If current is terminal and !== target: throw InvalidConversionTransition
  - If current === Pending: persist target status, return refreshed model

- [x] 2.2 Register exception render in `bootstrap/app.php`
  - Add InvalidConversionTransition handler
  - Return 409 JSON with message and errors.status array

- [x] 2.3 Verify exception handler does not leak internal details

---

## Phase 3: Controller & Routes (5 tasks)

- [x] 3.1 Add `approve` method to `app/Http/Controllers/Api/V1/ConversionController.php`
  - Authorize via CampaignPolicy->approveConversion
  - Resolve conversion through campaign relationship (not global lookup)
  - Call ReviewConversionAction with ConversionStatus::Approved
  - Return ConversionResource response

- [x] 3.2 Add `reject` method to `app/Http/Controllers/Api/V1/ConversionController.php`
  - Authorize via CampaignPolicy->rejectConversion
  - Resolve conversion through campaign relationship (not global lookup)
  - Call ReviewConversionAction with ConversionStatus::Rejected
  - Return ConversionResource response

- [x] 3.3 Register approve route in `routes/api.php`
  - `POST /campaigns/{campaign}/conversions/{conversion}/approve`
  - Name: `api.v1.campaigns.conversions.approve`
  - Inside existing auth:sanctum group

- [x] 3.4 Register reject route in `routes/api.php`
  - `POST /campaigns/{campaign}/conversions/{conversion}/reject`
  - Name: `api.v1.campaigns.conversions.reject`
  - Inside existing auth:sanctum group

- [x] 3.5 Verify no duplicate route names

---

## Phase 4: Security Tests (8 tasks)

- [x] 4.1 Guest approve → 401
- [x] 4.2 Guest reject → 401
- [x] 4.3 Foreign Affiliate approve → 403
- [x] 4.4 Foreign Affiliate reject → 403
- [x] 4.5 Admin on foreign Campaign approve → 403
- [x] 4.6 Admin on foreign Campaign reject → 403
- [x] 4.7 Wrong-parent Conversion approve → 404
- [x] 4.8 Wrong-parent Conversion reject → 404

---

## Phase 5: Approve Tests (7 tasks)

- [x] 4.9 Pending → Approved → 200
- [x] 4.10 Approved status persisted in database
- [x] 4.11 Response uses ConversionResource
- [x] 4.12 Revenue unchanged after approval
- [x] 4.13 converted_at unchanged after approval
- [x] 4.14 Approved → Approved → 200 idempotent
- [x] 4.15 Same-state retry does not create unexpected side effects

---

## Phase 6: Reject Tests (6 tasks)

- [x] 4.16 Pending → Rejected → 200
- [x] 4.17 Rejected status persisted in database
- [x] 4.18 Response uses ConversionResource
- [x] 4.19 Revenue unchanged after rejection
- [x] 4.20 converted_at unchanged after rejection
- [x] 4.21 Rejected → Rejected → 200 idempotent

---

## Phase 7: Conflict Tests (3 tasks)

- [x] 4.22 Approved → Rejected → 409
- [x] 4.23 Rejected → Approved → 409
- [x] 4.24 Conflict does not modify stored Conversion

---

## Phase 8: Revenue Snapshot Tests (3 tasks)

- [x] 4.25 Conversion created with revenue 25.00
- [x] 4.26 Offer payout later changed to 50.00
- [x] 4.27 Approving Conversion keeps revenue 25.00

---

## Phase 9: Dashboard Tests (7 tasks)

- [x] 4.28 Pending contributes to conversion_count
- [x] 4.29 Pending contributes zero revenue
- [x] 4.30 Approving increases revenue
- [x] 4.31 Approving increases profit
- [x] 4.32 conversion_count remains unchanged after approval
- [x] 4.33 Rejected Conversion contributes zero revenue
- [x] 4.34 Rejection does not change profit

---

## Phase 10: Period Tests (3 tasks)

- [x] 4.35 Approval does not change converted_at
- [x] 4.36 Old Conversion approved today remains in its original period
- [x] 4.37 Today's period does not include it solely because review happened today

---

## Phase 11: Concurrency Tests (4 tasks)

- [x] 4.38 Action uses current persisted state under lock
- [x] 4.39 Competing Approve/Reject cannot silently overwrite
- [x] 4.40 Second opposite transition receives 409
- [x] 4.41 Concurrent same-target transition behaves idempotently

---

## Phase 12: Regression Tests (5 tasks)

- [x] 4.42 Existing Conversion recording behavior still creates Pending
- [x] 4.43 Duplicate external_id behavior remains 409
- [x] 4.44 Dashboard existing tests still pass
- [x] 4.45 Campaign ownership security remains unchanged
- [x] 4.46 Admin no-business-bypass remains true

---

## Phase 13: Newman Collection (3 tasks)

- [x] 5.1 Create `postman/CPAFlow-AI-CONVERSION-REVIEW.postman_collection.json`
- [x] 5.2 Collection covers full review flow (approve, reject, idempotent, conflict, security)
- [x] 5.3 Collection passes with zero failures

---

## Phase 14: Documentation (2 tasks)

- [x] 6.1 Update `docs/conception-technique.md` with conversion lifecycle documentation
- [x] 6.2 Document state transitions, terminal states, idempotency, revenue snapshot, period behavior

---

## Phase 15: Final Verification (4 tasks)

- [x] 7.1 Run `php artisan test` — all tests pass
- [x] 7.2 Run `./vendor/bin/pint --test` — code style OK
- [x] 7.3 Run `composer validate` — no issues
- [x] 7.4 Run `npm run build` — assets compile
