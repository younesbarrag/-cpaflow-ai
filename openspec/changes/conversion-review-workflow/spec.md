# Specification: Conversion Review Workflow

## API Endpoints

### 1. Approve Conversion

**Endpoint:** `POST /api/v1/campaigns/{campaign}/conversions/{conversion}/approve`

**Authorization:** Authenticated user, campaign owner

**Response 200 OK:**
```json
{
    "data": {
        "id": 1,
        "campaign_id": 1,
        "external_id": "ext_123",
        "source": "google",
        "revenue": "50.00",
        "status": "Approved",
        "converted_at": "2026-08-03T12:00:00.000000Z"
    }
}
```

**Response 401 Unauthorized:**
```json
{
    "message": "Unauthenticated."
}
```

**Response 403 Forbidden:**
```json
{
    "message": "Forbidden."
}
```

**Response 404 Not Found:**
```json
{
    "message": "No query results for model [App\\Models\\Conversion]."
}
```

**Response 409 Conflict:**
```json
{
    "message": "The conversion transition is not allowed.",
    "errors": {
        "status": ["Conversion cannot transition from Approved to Approved."]
    }
}
```

**Behavior:**
1. Verify authentication (401 if missing)
2. Verify user owns the campaign via offer.user_id (403 if not)
3. Resolve conversion through `$campaign->conversions()` (404 if missing or wrong-parent)
4. Open DB transaction, lockForUpdate on conversion row
5. Read current persisted status
6. If current === target (Approved): return existing conversion, 200
7. If current is another terminal state: throw InvalidConversionTransition, 409
8. If current === Pending: persist Approved, return refreshed conversion, 200

---

### 2. Reject Conversion

**Endpoint:** `POST /api/v1/campaigns/{campaign}/conversions/{conversion}/reject`

**Authorization:** Authenticated user, campaign owner

**Response 200 OK:**
```json
{
    "data": {
        "id": 1,
        "campaign_id": 1,
        "external_id": "ext_123",
        "source": "google",
        "revenue": "50.00",
        "status": "Rejected",
        "converted_at": "2026-08-03T12:00:00.000000Z"
    }
}
```

**Response 401 Unauthorized:**
```json
{
    "message": "Unauthenticated."
}
```

**Response 403 Forbidden:**
```json
{
    "message": "Forbidden."
}
```

**Response 404 Not Found:**
```json
{
    "message": "No query results for model [App\\Models\\Conversion]."
}
```

**Response 409 Conflict:**
```json
{
    "message": "The conversion transition is not allowed.",
    "errors": {
        "status": ["Conversion cannot transition from Rejected to Rejected."]
    }
}
```

**Behavior:**
1. Verify authentication (401 if missing)
2. Verify user owns the campaign via offer.user_id (403 if not)
3. Resolve conversion through `$campaign->conversions()` (404 if missing or wrong-parent)
4. Open DB transaction, lockForUpdate on conversion row
5. Read current persisted status
6. If current === target (Rejected): return existing conversion, 200
7. If current is another terminal state: throw InvalidConversionTransition, 409
8. If current === Pending: persist Rejected, return refreshed conversion, 200

---

## HTTP Status Matrix

| Scenario | HTTP Status |
|----------|-------------|
| Not authenticated | 401 |
| Not campaign owner | 403 |
| Conversion not found | 404 |
| Wrong-parent Conversion | 404 |
| Pending → Approved | 200 |
| Pending → Rejected | 200 |
| Approved → Approved | 200 (idempotent) |
| Rejected → Rejected | 200 (idempotent) |
| Approved → Rejected | 409 |
| Rejected → Approved | 409 |

---

## Revenue Snapshot Behavior

- Approval must NEVER recalculate revenue
- `Conversion.revenue` is snapshotted from `Offer.payout` at creation time
- If `Offer.payout` changes after creation, existing Conversions retain their original revenue
- Dashboard Approved revenue uses stored `Conversion.revenue`

---

## converted_at Semantics

- `converted_at` represents when the conversion occurred
- Reviewing a Conversion must NOT change `converted_at`
- No `updated_at`-based period shifting

---

## Dashboard Impact

| Metric | Pending | After Approval | After Rejection |
|--------|---------|----------------|-----------------|
| conversion_count | includes | unchanged | unchanged |
| revenue | excluded | increases by stored revenue | unchanged |
| profit | excluded | increases by stored revenue | unchanged |

---

## Period Filter Behavior

- KAN-19 uses `converted_at` for period filtering
- Approval does NOT move Conversion to another period
- `updated_at` changes do not affect period placement

---

## Security Matrix

| Actor | Approve | Reject |
|-------|---------|--------|
| Guest (unauthenticated) | 401 | 401 |
| Campaign owner | 200 | 200 |
| Foreign Affiliate | 403 | 403 |
| Admin on foreign Campaign | 403 | 403 |
| Unknown Campaign | 404 | 404 |
| Wrong-parent Conversion | 404 | 404 |
| Same-state retry | 200 | 200 |
| Opposite terminal | 409 | 409 |

---

## Scope Exclusions

Explicitly excluded from this feature:
- Conversion deletion
- Conversion editing
- Revenue editing
- external_id / source / converted_at editing
- Approval comments
- Rejection reason
- reviewed_by / approved_at / rejected_at columns
- Review history / audit table
- Notifications
- Bulk approval
- Automatic approval rules
- Fraud detection
- Webhooks
- Admin business dashboard
- Blade UI / UI redesign
- Docker / deployment changes
- CI modification
- Dependency changes
