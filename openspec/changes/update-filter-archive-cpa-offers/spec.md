# Specification — KAN-12: Modifier, filtrer et archiver ses offres CPA

## Requirements

### R1 — Ownership Authorization

| ID | Requirement | Jira Criterion |
|----|-------------|----------------|
| R1.1 | Only the authenticated user who owns an offer can update it | Criterion 1, 6 |
| R1.2 | Only the authenticated user who owns an offer can archive it | Criterion 1, 6 |
| R1.3 | Accessing another user's offer returns HTTP 403 | Criterion 6 |
| R1.4 | Guest access returns HTTP 401 | Criterion 1 |

### R2 — Offer Status Management

| ID | Requirement | Jira Criterion |
|----|-------------|----------------|
| R2.1 | Status values use the `OfferStatus` enum: draft, active, suspended, archived | Criterion 2 |
| R2.2 | Archival sets status to `OfferStatus::Archived` | Criterion 3 |
| R2.3 | Archival is idempotent — archiving an already-archived offer returns 200 | Criterion 3 |
| R2.4 | No physical deletion of offers | Criterion 3 |
| R2.5 | No SoftDeletes used | Criterion 3 |

### R3 — Offer Update

| ID | Requirement | Jira Criterion |
|----|-------------|----------------|
| R3.1 | Updatable fields: name, destination_url, payout, status, description | Criterion 1 |
| R3.2 | Ownership fields (user_id, owner_id, affiliate_id) are never editable | Criterion 1 |
| R3.3 | Partial updates supported — only submitted fields are validated and updated | Criterion 1 |
| R3.4 | Same validation rules as creation where applicable | Criterion 1 |

### R4 — Filtering and Search

| ID | Requirement | Jira Criterion |
|----|-------------|----------------|
| R4.1 | Listing supports `?status=<value>` filter | Criterion 4 |
| R4.2 | Listing supports `?search=<term>` filter | Criterion 5 |
| R4.3 | Combined status + search is supported | Criterion 4, 5 |
| R4.4 | Invalid status returns HTTP 422 | Criterion 4 |
| R4.5 | Filters never expose another user's offers | Criterion 1, 6 |
| R4.6 | Pagination remains at 15 items per page | Existing behavior |
| R4.7 | Ordering remains by id descending | Existing behavior |

---

## Scenarios

### S1 — Update Authorization

**Given** an authenticated user owns offer #1
**When** the user sends `PATCH /api/v1/offers/1` with valid data
**Then** the offer is updated and HTTP 200 is returned

**Given** an authenticated user does NOT own offer #2
**When** the user sends `PATCH /api/v1/offers/2` with valid data
**Then** HTTP 403 is returned and the offer is unchanged

**Given** no user is authenticated
**When** a guest sends `PATCH /api/v1/offers/1`
**Then** HTTP 401 is returned

### S2 — Update Validation

**Given** an authenticated user owns an offer
**When** the user sends `PATCH /api/v1/offers/{id}` with `{"name": "  New Name  "}`
**Then** the name is trimmed and stored as "New Name"

**Given** an authenticated user owns an offer
**When** the user sends `PATCH /api/v1/offers/{id}` with `{"destination_url": "ftp://evil.com"}`
**Then** HTTP 422 is returned with `destination_url` validation error

**Given** an authenticated user owns an offer
**When** the user sends `PATCH /api/v1/offers/{id}` with `{"payout": "-10"}`
**Then** HTTP 422 is returned with `payout` validation error

**Given** an authenticated user owns an offer
**When** the user sends `PATCH /api/v1/offers/{id}` with `{"status": "bogus"}`
**Then** HTTP 422 is returned with `status` validation error

**Given** an authenticated user owns an offer
**When** the user sends `PATCH /api/v1/offers/{id}` with `{"user_id": 999}`
**Then** the user_id field is ignored; ownership does not change

### S3 — Archive

**Given** an authenticated user owns a non-archived offer
**When** the user sends `POST /api/v1/offers/{id}/archive`
**Then** status becomes "archived" and HTTP 200 is returned

**Given** an authenticated user owns an already-archived offer
**When** the user sends `POST /api/v1/offers/{id}/archive`
**Then** HTTP 200 is returned (idempotent, no error)

**Given** an authenticated user does NOT own an offer
**When** the user sends `POST /api/v1/offers/{id}/archive`
**Then** HTTP 403 is returned

**Given** no user is authenticated
**When** a guest sends `POST /api/v1/offers/{id}/archive`
**Then** HTTP 401 is returned

### S4 — Filtering

**Given** an authenticated user has offers with statuses "draft", "active", "archived"
**When** the user sends `GET /api/v1/offers?status=active`
**Then** only offers with status "active" are returned

**Given** an authenticated user has 3 offers
**When** the user sends `GET /api/v1/offers?status=invalid`
**Then** HTTP 422 is returned with `status` validation error

**Given** an authenticated user has offers named "Fitness Trial" and "VPN Deal"
**When** the user sends `GET /api/v1/offers?search=fitness`
**Then** only "Fitness Trial" is returned

**Given** an authenticated user has offers with different statuses and names
**When** the user sends `GET /api/v1/offers?status=draft&search=fitness`
**Then** only draft offers with "fitness" in the name are returned

**Given** user A has 20 offers and user B has 5 offers
**When** user A sends `GET /api/v1/offers`
**Then** only user A's offers are returned (20 total), paginated at 15

### S5 — Policy guarantees 403 not 404

**Given** offer #999 exists and belongs to user B
**When** user A sends `PATCH /api/v1/offers/999`
**Then** HTTP 403 is returned (not 404)

**Given** offer #999 exists and belongs to user B
**When** user A sends `POST /api/v1/offers/999/archive`
**Then** HTTP 403 is returned (not 404)

---

## Acceptance Criteria Mapping

| Jira Criterion | Requirement | Scenario | Task | Test |
|----------------|-------------|----------|------|------|
| 1. Only owner can update or archive | R1.1, R1.2 | S1, S3 | T1.1, T5.1, T5.2 | T7.1, T7.3 |
| 2. Status values use OfferStatus | R2.1 | S2 | T3.1 | T7.2 |
| 3. Archival uses coherent strategy | R2.2, R2.3, R2.4, R2.5 | S3 | T2.2, T5.2 | T7.3 |
| 4. List filterable by status | R4.1, R4.4 | S4 | T3.2, T4.1, T5.3 | T7.4 |
| 5. List searchable by name | R4.2, R4.3 | S4 | T3.2, T4.2, T5.3 | T7.4 |
| 6. Foreign offer returns 403 | R1.3 | S5 | T1.1, T5.1, T5.2 | T7.5 |

---

## HTTP Contracts

### PATCH /api/v1/offers/{offer}

**Request:**

```http
PATCH /api/v1/offers/1
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Updated Offer Name",
    "destination_url": "https://new-url.com",
    "payout": "15.00",
    "status": "active",
    "description": "Updated description"
}
```

**Response 200:**

```json
{
    "data": {
        "offer": {
            "id": 1,
            "name": "Updated Offer Name",
            "destination_url": "https://new-url.com",
            "payout": "15.00",
            "status": "active",
            "description": "Updated description",
            "created_at": "2026-07-21T00:00:00.000000Z",
            "updated_at": "2026-07-22T00:00:00.000000Z"
        }
    }
}
```

**Response 403 (foreign offer):**

```json
{
    "message": "Forbidden."
}
```

**Response 422 (validation error):**

```json
{
    "message": "The destination_url field must be a valid URL.",
    "errors": {
        "destination_url": ["The destination_url field must be a valid URL."]
    }
}
```

### POST /api/v1/offers/{offer}/archive

**Request:**

```http
POST /api/v1/offers/1/archive
Authorization: Bearer {token}
```

**Response 200:**

```json
{
    "data": {
        "offer": {
            "id": 1,
            "name": "Offer Name",
            "destination_url": "https://example.com",
            "payout": "10.00",
            "status": "archived",
            "description": "Description",
            "created_at": "2026-07-21T00:00:00.000000Z",
            "updated_at": "2026-07-22T00:00:00.000000Z"
        }
    }
}
```

**Response 403 (foreign offer):**

```json
{
    "message": "Forbidden."
}
```

### GET /api/v1/offers?status={status}&search={search}

**Request:**

```http
GET /api/v1/offers?status=active&search=fitness
Authorization: Bearer {token}
```

**Response 200:**

```json
{
    "data": [
        {
            "id": 1,
            "name": "Fitness Trial",
            "destination_url": "https://example.com/fitness",
            "payout": "25.50",
            "status": "active",
            "description": null,
            "created_at": "2026-07-21T00:00:00.000000Z",
            "updated_at": "2026-07-21T00:00:00.000000Z"
        }
    ],
    "links": {
        "first": "http://localhost/api/v1/offers?status=active&search=fitness&page=1",
        "last": "http://localhost/api/v1/offers?status=active&search=fitness&page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

**Response 422 (invalid status):**

```json
{
    "message": "The selected status is invalid.",
    "errors": {
        "status": ["The selected status is invalid."]
    }
}
```
