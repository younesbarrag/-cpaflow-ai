# Specification - KAN-16: Enregistrer une conversion sans doublon

## 1. Functional Requirements

### R1 - Conversion Recording

| ID | Requirement |
|---|---|
| R1.1 | `POST /api/v1/campaigns/{campaign}/conversions` records a Conversion. |
| R1.2 | The route is authenticated and requires `auth:sanctum`. |
| R1.3 | The route is registered in `routes/api.php` under `/api/v1`. |
| R1.4 | The route name is `api.v1.campaigns.conversions.store`. |
| R1.5 | A valid request creates exactly one Conversion row. |
| R1.6 | The Conversion is linked to the route-bound Campaign. |

### R2 - Duplicate Prevention

| ID | Requirement |
|---|---|
| R2.1 | `external_id` is NOT NULL and has a UNIQUE database constraint. |
| R2.2 | A conversion with a duplicate `external_id` is rejected. |
| R2.3 | Duplicate rejection returns `409 Conflict`. |
| R2.4 | The database UNIQUE constraint prevents duplicates under concurrent requests. |
| R2.5 | `UniqueConstraintViolationException` is caught and converted to `DuplicateConversionException`. |
| R2.6 | No two rows with the same `external_id` can coexist in the database. |
| R2.7 | The Form Request does NOT use Laravel's `unique` validation rule on `external_id`. |

### R3 - Revenue Snapshotting

| ID | Requirement |
|---|---|
| R3.1 | `revenue` is snapshotted from `Offer.payout` at conversion time. |
| R3.2 | The client cannot submit a custom `revenue` value. |
| R3.3 | `revenue` is DECIMAL(12,2) with NO database default; the application supplies the trusted value from `Offer.payout`. |
| R3.4 | If the Offer payout changes later, existing conversions retain their snapshot. |

### R4 - Status Management

| ID | Requirement |
|---|---|
| R4.1 | New conversions default to `pending` status. |
| R4.2 | `status` uses the existing `ConversionStatus` enum. |
| R4.3 | Status transitions (approve/reject) are not part of KAN-16. |

### R5 - Converted At

| ID | Requirement |
|---|---|
| R5.1 | `converted_at` is required in the database. |
| R5.2 | `converted_at` is server-generated as `now()` when the conversion is recorded. |
| R5.3 | `converted_at` is NOT accepted from the client request. |

### R6 - Source

| ID | Requirement |
|---|---|
| R6.1 | `source` is optional and nullable. |
| R6.2 | `source` is a string with max 255 characters when provided. |
| R6.3 | `source` is informational only and does not participate in financial trust or authorization. |

### R7 - Ownership and Authorization

| ID | Requirement |
|---|---|
| R7.1 | The authenticated user must own the Campaign through `Campaign → Offer → User`. |
| R7.2 | Foreign Campaign returns `403 Forbidden`. |
| R7.3 | Guest request returns `401 Unauthorized`. |
| R7.4 | Unknown Campaign returns `404 Not Found`. |

### R8 - Validation

| ID | Requirement |
|---|---|
| R8.1 | `external_id` is required. |
| R8.2 | `external_id` is a string with max 255 characters. |
| R8.3 | Missing `external_id` returns `422` with `errors.external_id`. |
| R8.4 | `source` is optional and validated as nullable string max 255 when provided. |
| R8.5 | Invalid `source` returns `422` with `errors.source`. |

### R9 - Response Shape

| ID | Requirement |
|---|---|
| R9.1 | Success response follows the `data.conversion` envelope. |
| R9.2 | Response contains `id`, `campaign_id`, `external_id`, `source`, `revenue`, `status`, `converted_at`, `created_at`, `updated_at`. |
| R9.3 | `status` is serialized as the enum string value. |
| R9.4 | `revenue` is serialized as a two-decimal string. |
| R9.5 | Timestamps are serialized as ISO 8601 strings. |
| R9.6 | Success HTTP status is `201 Created`. |

### R10 - Relationships

| ID | Requirement |
|---|---|
| R10.1 | Campaign has many Conversions (`Campaign::conversions()`). |
| R10.2 | Conversion belongs to one Campaign (`Conversion::campaign()`). |
| R10.3 | Deleting a Campaign cascades to its Conversions. |

## 2. Behavioral Scenarios

### S1 - Valid conversion is recorded

**Given** an authenticated user owns a Campaign
**When** the user submits `POST /api/v1/campaigns/{id}/conversions` with a valid `external_id`
**Then** one Conversion is persisted with the correct `campaign_id`, `external_id`, snapshotted `revenue`, `status = pending`, and `converted_at` set.

### S2 - Success response has correct shape

**Given** a valid conversion request
**When** the conversion is recorded
**Then** the response is `201` with `data.conversion` containing `id`, `campaign_id`, `external_id`, `source`, `revenue`, `status`, `converted_at`, `created_at`, `updated_at`.

### S3 - Duplicate external_id returns 409

**Given** a Conversion exists with `external_id = "TXN-001"`
**When** the user submits another conversion with `external_id = "TXN-001"` for the same Campaign
**Then** the response is `409 Conflict` and no second Conversion is created.

### S4 - Database uniqueness prevents duplicates

**Given** a Conversion exists with `external_id = "TXN-001"`
**When** two concurrent requests submit `external_id = "TXN-001"`
**Then** exactly one Conversion exists with `external_id = "TXN-001"` in the database.

### S5 - Different external_id creates another conversion

**Given** a Conversion exists with `external_id = "TXN-001"`
**When** the user submits a conversion with `external_id = "TXN-002"`
**Then** a new Conversion is created and the response is `201`.

### S6 - Unknown campaign returns 404

**Given** no Campaign exists with ID `99999`
**When** a user submits `POST /api/v1/campaigns/99999/conversions`
**Then** the response is `404 Not Found`.

### S7 - Invalid payload returns 422

**Given** an authenticated user owns a Campaign
**When** the user submits `POST /api/v1/campaigns/{id}/conversions` without `external_id`
**Then** the response is `422` with `errors.external_id`.

### S8 - Required fields are validated

**Given** an authenticated user owns a Campaign
**When** the user submits `external_id` as an empty string
**Then** the response is `422` with `errors.external_id`.

### S9 - Foreign ownership returns 403

**Given** an authenticated user does NOT own the Campaign
**When** the user submits `POST /api/v1/campaigns/{id}/conversions`
**Then** the response is `403 Forbidden`.

### S10 - Guest request returns 401

**Given** no user is authenticated
**When** a request is made to `POST /api/v1/campaigns/{id}/conversions`
**Then** the response is `401 Unauthorized`.

### S11 - Client cannot forge revenue

**Given** an authenticated user owns a Campaign with Offer.payout = 25.00
**When** the user submits a conversion (revenue is not in the request body)
**Then** the persisted Conversion has `revenue = 25.00` (snapshotted from Offer).

### S12 - converted_at is server-generated

**Given** a valid conversion request
**When** the conversion is recorded
**Then** the persisted `converted_at` is approximately the current time (server-generated).

### S13 - source is accepted when provided

**Given** a valid conversion request with `source = "postback"`
**When** the conversion is recorded
**Then** the persisted Conversion has `source = "postback"`.

### S14 - source is null when not provided

**Given** a valid conversion request without `source`
**When** the conversion is recorded
**Then** the persisted Conversion has `source = null`.

### S15 - Campaign::conversions() works

**Given** a Campaign with 3 persisted Conversions
**When** `$campaign->conversions` is accessed
**Then** it returns exactly 3 Conversion models.

### S16 - Conversion::campaign() works

**Given** a Conversion persisted for a specific Campaign
**When** `$conversion->campaign` is accessed
**Then** it returns the correct Campaign model.

### S17 - Cascade deletion works

**Given** a Campaign with persisted Conversions
**When** the Campaign is deleted
**Then** all its Conversions are also deleted.

### S18 - KAN-14 generation remains unaffected

**Given** the existing KAN-14 tracking link generation endpoint
**When** an owner generates a tracking link for an active Campaign
**Then** the behavior, response format, and database state are unchanged.

### S19 - KAN-15 click/redirect remains unaffected

**Given** the existing KAN-15 redirect endpoint
**When** a visitor opens `GET /t/{code}` for a valid active link
**Then** the behavior, response, and click recording are unchanged.

### S20 - Postman/Newman validates the real flow

**Given** a Postman Collection v2.1 with the KAN-16 conversion tests
**When** the collection is run against a local environment
**Then** all tests pass: record conversion, duplicate 409, different conversion 201, unknown campaign 404.

## 3. HTTP Contracts

### 3.1 Route

| Method | Endpoint | Name | Success |
|---|---|---|---:|
| POST | `/api/v1/campaigns/{campaign}/conversions` | `api.v1.campaigns.conversions.store` | `201` |

### 3.2 Request

| Field | Type | Required | Notes |
|---|---|---|---|
| `external_id` | string | Yes | Max 255, unique at DB level |
| `source` | string | No | Max 255, nullable, informational |

### 3.3 Success Response (201)

```json
{
    "data": {
        "conversion": {
            "id": 1,
            "campaign_id": 1,
            "external_id": "TXN-2026-001234",
            "source": "postback",
            "revenue": "25.00",
            "status": "pending",
            "converted_at": "2026-08-03T10:30:01.000000Z",
            "created_at": "2026-08-03T10:30:01.000000Z",
            "updated_at": "2026-08-03T10:30:01.000000Z"
        }
    }
}
```

### 3.4 Duplicate Response (409)

```json
{
    "message": "A conversion with this external ID already exists.",
    "errors": {
        "external_id": [
            "A conversion with external ID \"TXN-2026-001234\" already exists."
        ]
    }
}
```

### 3.5 Validation Error Response (422)

```json
{
    "message": "The provided data was invalid.",
    "errors": {
        "external_id": ["The external id field is required."]
    }
}
```

### 3.6 Foreign Ownership Response (403)

Standard Laravel 403 response.

### 3.7 Unknown Campaign Response (404)

Standard Laravel 404 response.

### 3.8 Guest Response (401)

Standard Laravel Sanctum 401 response.

### 3.9 Response precedence

| Condition | Response |
|---|---:|
| Guest | `401` |
| Unknown Campaign | `404` |
| Foreign Campaign | `403` |
| Missing external_id | `422` |
| Duplicate external_id | `409` |
| Valid conversion | `201` |

## 4. Acceptance Mapping

| Jira acceptance criterion | Requirements | Scenarios |
|---|---|---|
| Record a conversion without duplicates | R1, R2 | S1, S2, S3, S4, S5 |
| Duplicate external_id returns 409 | R2.3 | S3, S4 |
| Valid conversion returns 201 | R1, R9 | S1, S2 |
| Unknown campaign returns 404 | R7.4 | S6 |
| Invalid payload returns 422 | R8 | S7, S8 |
| Foreign ownership is protected | R7.2 | S9 |
| Guest returns 401 | R7.3 | S10 |
| Client cannot forge revenue | R3.2 | S11 |
| converted_at is server-generated | R5.2, R5.3 | S12 |
| source is optional | R6 | S13, S14 |
| Relationships work | R10 | S15, S16, S17 |
| KAN-14/KAN-15 unaffected | — | S18, S19 |
| Postman validates real flow | — | S20 |

## 5. Explicit Exclusions

Conversion dashboard, conversion status transitions, attribution analytics, period filters, campaign expenses, AI features, conversion editing, conversion deletion, batch import, frontend conversion UI, public postback endpoint, refunds, chargebacks, fraud detection, unique visitor counting, tracking link/click attribution, and tracking link/click as prerequisite are not part of KAN-16.
