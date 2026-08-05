# Design: Conversion Review Workflow

## Architecture Decisions

### 1. Endpoint Design
**Decision:** Explicit POST endpoints per action
```
POST /api/v1/campaigns/{campaign}/conversions/{conversion}/approve
POST /api/v1/campaigns/{campaign}/conversions/{conversion}/reject
```

**Rationale:** Follows existing pattern (activate/suspend for campaigns). Clear intent, explicit API contract, no ambiguity about what status change is requested. No generic PATCH endpoint. No client-supplied status field.

### 2. Authorization Model
**Decision:** Owner-based via CampaignPolicy

**Implementation:**
- Add `approveConversion` method to CampaignPolicy
- Add `rejectConversion` method to CampaignPolicy
- Both reuse existing `ownsCampaign()` (user.id === campaign.offer.user_id)

**Rationale:** Follows existing patterns. Campaign owner manages their own conversions. Admin role does not grant business-resource bypass.

**Expected behavior:**
| Actor | Result |
|-------|--------|
| Guest (unauthenticated) | 401 |
| Foreign Affiliate | 403 |
| Admin on foreign Campaign | 403 |
| Campaign owner | allowed |

### 3. Single Domain Action
**Decision:** One `ReviewConversionAction` instead of separate approve/reject actions

**Signature:**
```php
ReviewConversionAction::execute(
    Campaign $campaign,
    int $conversionId,
    ConversionStatus $targetStatus,
): Conversion
```

**Rationale:** Both endpoints share identical transaction/locking/lookup logic. Only the target status differs. Single action avoids duplication.

### 4. State Transition Rules
**Decision:** Pending → Approved/Rejected only. Approved and Rejected are terminal.

```
                    ┌─────────────┐
                    │   Pending   │
                    └──────┬──────┘
                           │
           ┌───────────────┴───────────────┐
           │                               │
           ▼                               ▼
    ┌──────────────┐                ┌──────────────┐
    │   Approved   │                │   Rejected   │
    └──────────────┘                └──────────────┘
         terminal                        terminal
```

**Allowed transitions:**
- `Pending` → `Approved`
- `Pending` → `Rejected`

**Forbidden transitions (return 409 Conflict):**
- `Approved` → `Rejected`
- `Rejected` → `Approved`

**No reopening. No rollback to Pending. No arbitrary status editing.**

### 5. Idempotency Contract
**Decision:** Same-state = 200 idempotent no-op. Opposite terminal = 409 Conflict.

| Current | Target | HTTP | Behavior |
|---------|--------|------|----------|
| Pending | Approved | 200 | Transition applied |
| Pending | Rejected | 200 | Transition applied |
| Approved | Approved | 200 | Idempotent no-op, return existing |
| Rejected | Rejected | 200 | Idempotent no-op, return existing |
| Approved | Rejected | 409 | Conflict: terminal state |
| Rejected | Approved | 409 | Conflict: terminal state |

**Rationale:** Same-action retries must be safe. No silent last-write-wins.

### 6. Transaction + Concurrency Strategy
**Decision:** DB::transaction with lockForUpdate

```php
DB::transaction(function () use (
    $campaign,
    $conversionId,
    $targetStatus,
) {
    $conversion = $campaign->conversions()
        ->whereKey($conversionId)
        ->lockForUpdate()
        ->firstOrFail();

    $currentStatus = $conversion->status;

    // Same-state: idempotent no-op
    if ($currentStatus === $targetStatus) {
        return $conversion;
    }

    // Opposite terminal: conflict
    if ($currentStatus !== ConversionStatus::Pending) {
        throw new InvalidConversionTransition(
            from: $currentStatus,
            to: $targetStatus,
        );
    }

    // Pending → target
    $conversion->status = $targetStatus;
    $conversion->save();

    return $conversion->refresh();
});
```

**Critical:** The route-bound Conversion instance must NOT be trusted. The Action must re-read the current persisted row under lock.

### 7. Concurrent Approve/Reject Behavior
**Scenario:** Request A (approve) and Request B (reject) arrive concurrently for the same Pending conversion.

**Expected:**
1. Transaction A acquires lock first
2. A reads Pending → Approved, commits
3. Transaction B acquires lock
4. B reads Approved, target is Rejected → 409 Conflict

**No silent last-write-wins. No duplicate effects.**

### 8. Concurrent Same-Target Behavior
**Scenario:** Two approve requests arrive concurrently.

**Expected:**
1. First: Pending → Approved → 200
2. Second: reads Approved, target is Approved → 200 idempotent

No new write required for second request.

### 9. Nested Resource Security
**Decision:** Conversion resolved through campaign relationship

```php
$campaign->conversions()->whereKey($conversionId)->firstOrFail();
```

**NOT:** `Conversion::find($conversionId)` then compare `campaign_id`.

**Wrong-parent example:**
- Conversion belongs to Campaign B
- Request: `POST /campaigns/A/conversions/{conversionB}/approve`
- Expected: 404

### 10. Exception Handling
**Decision:** New `InvalidConversionTransition` exception

- Extends `DomainException`
- Contains `$from` and `$to` ConversionStatus properties
- Renders 409 JSON via `bootstrap/app.php`
- Consistent with existing `InvalidCampaignTransition`

### 11. Revenue Snapshot Rule
**Decision:** Approval/rejection must NEVER recalculate revenue

KAN-16 snapshots `Conversion.revenue` from `Offer.payout` at creation time. If `Offer.payout` changes later, existing Conversions retain their original revenue. Dashboard revenue uses stored `Conversion.revenue`.

### 12. converted_at Semantics
**Decision:** Reviewing a Conversion must NOT change `converted_at`

`converted_at` represents when the conversion occurred, not when it was reviewed. No approval timestamps in this MVP. No `approved_at`, `rejected_at`, `reviewed_at`, `reviewed_by` columns.

### 13. Dashboard Impact
**No production-code changes required.** Current queries are correct:
- `conversion_count`: counts all statuses (unchanged)
- `revenue`: sums only `Approved` conversions (increases when conversion approved)
- `profit`: `Approved revenue - CampaignExpenses` (increases when conversion approved)

### 14. Period Filter Impact
**No production-code changes required.** KAN-19 uses `converted_at` for period filtering. Approval time must NOT move a Conversion into another reporting period. `updated_at` changes do not affect period placement.

### 15. Resource Response
**Decision:** Reuse existing `ConversionResource`

Successful transition returns HTTP 200 with the Conversion resource. No new resource class. No internal-only data exposure.

### 16. Request Validation
**Decision:** No Form Request required

Endpoints require no public body fields. Authorization occurs via Policy before Action. Route model binding handles lookup.

### 17. Database Decision
**Decision:** NO migration

Existing Conversion schema already supports the workflow. Status column defaults to `Pending`. Enum values `Pending`, `Approved`, `Rejected` exist.

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `app/Exceptions/InvalidConversionTransition.php` | CREATE |
| `app/Actions/Conversion/ReviewConversionAction.php` | CREATE |
| `app/Policies/CampaignPolicy.php` | MODIFY — add `approveConversion`, `rejectConversion` |
| `app/Http/Controllers/Api/V1/ConversionController.php` | MODIFY — add `approve`, `reject` methods |
| `bootstrap/app.php` | MODIFY — add `InvalidConversionTransition` render |
| `routes/api.php` | MODIFY — add approve/reject routes |
| `tests/Feature/Api/V1/ConversionApiTest.php` | MODIFY — add review tests |
| `docs/conception-technique.md` | MODIFY — document conversion lifecycle |
