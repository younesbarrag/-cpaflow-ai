# Specification - KAN-14: Generer un lien de tracking pour une campagne

## 1. Functional Requirements

### R1 - Persistence and Ownership

| ID | Requirement |
|---|---|
| R1.1 | A TrackingLink belongs to exactly one Campaign through non-null `campaign_id`. |
| R1.2 | TrackingLink ownership is derived from the parent Campaign's Offer's `user_id`; TrackingLink has no direct `user_id`. |
| R1.3 | `campaign_id` is set through the Campaign relationship and is immutable after creation. |
| R1.4 | Deleting a Campaign cascades to its TrackingLinks. |
| R1.5 | TrackingLink generation is isolated to the authenticated Campaign owner. |

### R2 - Code Uniqueness

| ID | Requirement |
|---|---|
| R2.1 | The tracking code is a 32-character URL-safe alphanumeric string. |
| R2.2 | The tracking code is generated server-side and is not client-submittable. |
| R2.3 | A database UNIQUE constraint enforces code uniqueness. |
| R2.4 | Application-level collision handling retries up to 5 times, but only for verified unique-constraint violations on `code`. |
| R2.5 | The generated code is not a predictable sequential value. |
| R2.6 | Unrelated database exceptions are rethrown immediately and never retried. |

### R3 - Generation

| ID | Requirement |
|---|---|
| R3.1 | `POST /api/v1/campaigns/{campaign}/tracking-links` creates a tracking link and returns `201`. |
| R3.2 | The Campaign must be `active` to generate a tracking link. |
| R3.3 | A `draft` Campaign returns `422` with a `status` validation error. |
| R3.4 | A `suspended` Campaign returns `422` with a `status` validation error. |
| R3.5 | The endpoint does not accept any body parameters. |

### R4 - Tracking URL

| ID | Requirement |
|---|---|
| R4.1 | The response includes a `url` field containing the full tracking URL. |
| R4.2 | The URL is constructed with `url('/t/' . $code)` using Laravel's URL generator. |
| R4.3 | KAN-14 does not implement the public redirect endpoint for this URL. |

### R5 - One or Multiple Links

| ID | Requirement |
|---|---|
| R5.1 | A Campaign may have multiple tracking links. |
| R5.2 | Repeated generation on the same Campaign creates a new independent tracking link. |
| R5.3 | The endpoint is intentionally non-idempotent. |

### R6 - Authorization

| ID | Requirement |
|---|---|
| R6.1 | The route uses `auth:sanctum`; guests receive `401`. |
| R6.2 | `GenerateTrackingLinkRequest::authorize()` checks `CampaignPolicy::generateTrackingLink` through `Campaign → Offer → User`. |
| R6.3 | There is no Admin bypass. |
| R6.4 | Authorization occurs before Campaign-status validation so a foreign Campaign returns `403` regardless of its status. |
| R6.5 | Missing Campaign returns `404` via route model binding before authorization. |

### R7 - Serialization

| ID | Requirement |
|---|---|
| R7.1 | `TrackingLinkResource` returns `id`, `campaign_id`, `code`, `url`, `created_at`, and `updated_at`. |
| R7.2 | The response does not expose `user_id`, `offer_id`, `destination_url`, Campaign name, Campaign budget, Campaign traffic source, or other unrelated fields. |
| R7.3 | The `url` field is a fully qualified URL string generated with `url()`. |

## 2. Behavioral Scenarios

### S1 - Generate under an active owned Campaign

**Given** an authenticated user owns an active Campaign  
**When** they submit `POST /api/v1/campaigns/{campaign}/tracking-links` with no body  
**Then** the API returns `201`, the TrackingLink belongs to that Campaign, the code is 32 characters and URL-safe, and the response includes the full tracking URL.

### S2 - Missing Campaign

**Given** a Campaign ID does not exist  
**When** an authenticated user generates a tracking link  
**Then** route model binding returns `404` and persists nothing.

### S3 - Foreign Campaign

**Given** an existing Campaign belongs to another user  
**When** an authenticated user generates a tracking link  
**Then** Form Request authorization returns `403` and persists nothing.

**Given** an existing foreign Campaign is `draft`  
**When** an authenticated user generates a tracking link  
**Then** authorization returns `403` (not `422`) and persists nothing.

**Given** an existing foreign Campaign is `suspended`  
**When** an authenticated user generates a tracking link  
**Then** authorization returns `403` (not `422`) and persists nothing.

### S4 - Draft Campaign

**Given** an owned `draft` Campaign  
**When** the owner generates a tracking link  
**Then** the API returns `422` with an `errors.status` error and persists nothing.

### S5 - Suspended Campaign

**Given** an owned `suspended` Campaign  
**When** the owner generates a tracking link  
**Then** the API returns `422` with an `errors.status` error and persists nothing.

### S6 - Repeated generation

**Given** an owned active Campaign with existing tracking links  
**When** the owner generates another tracking link  
**Then** the API returns `201` with a new, independent tracking link, and all previous links remain unchanged.

### S7 - Code uniqueness

**Given** two tracking links are generated for different Campaigns  
**When** both are persisted  
**Then** their codes are different.

### S8 - Collision retry

**Given** the first code generation attempt produces a verified unique-constraint violation on `code`  
**When** the Action retries  
**Then** a new code is generated, the INSERT succeeds, and the response is `201`.

**Given** a database exception occurs that is NOT a unique-constraint violation  
**When** the Action encounters it  
**Then** the exception is rethrown immediately without retry.

**Given** all 5 retry attempts produce verified unique-constraint violations  
**When** the Action cannot generate a unique code  
**Then** a domain-level generation exception is thrown and a `500` is returned.

### S9 - Guest access

**Given** no user is authenticated  
**When** tracking link generation is requested  
**Then** Sanctum returns `401` and persists nothing.

### S10 - Response shape

**Given** an owner successfully generates a tracking link  
**When** the response is inspected  
**Then** it contains exactly `id`, `campaign_id`, `code`, `url`, `created_at`, and `updated_at`, with no `user_id`, `offer_id`, `destination_url`, `is_active`, `name`, `budget`, or `traffic_source`.

## 3. HTTP Contracts

### 3.1 Route

| Method | Endpoint | Name | Success |
|---|---|---|---:|
| POST | `/api/v1/campaigns/{campaign}/tracking-links` | `api.v1.campaigns.tracking-links.store` | `201` |

### 3.2 Request

No body parameters. The Campaign is identified by the route parameter.

### 3.3 Success response

```json
{
  "data": {
    "tracking_link": {
      "id": 1,
      "campaign_id": 42,
      "code": "aB3dE7gH9jK1mN3pQ5rS7tU9vW1xY3z",
      "url": "http://localhost/t/aB3dE7gH9jK1mN3pQ5rS7tU9vW1xY3z",
      "created_at": "2026-07-29T12:00:00.000000Z",
      "updated_at": "2026-07-29T12:00:00.000000Z"
    }
  }
}
```

### 3.4 Error responses

#### Guest (401)

```json
{
  "message": "Unauthenticated."
}
```

#### Missing Campaign (404)

```json
{
  "message": "No query results for model [App\\Models\\Campaign]."
}
```

#### Foreign Campaign (403)

```json
{
  "message": "Forbidden."
}
```

#### Draft or Suspended Campaign (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": [
      "Only an active campaign can generate tracking links."
    ]
  }
}
```

### 3.5 Error precedence

| Request condition | Required response |
|---|---:|
| Guest | `401` |
| Valid-shaped but missing Campaign ID | `404` |
| Existing foreign Campaign | `403` |
| Owned draft Campaign | `422` |
| Owned suspended Campaign | `422` |
| Code collision after max retries | `500` |

## 4. Acceptance Mapping

| Jira acceptance criterion | Requirements | Scenarios |
|---|---|---|
| Campaign belongs to authenticated user | R1, R6 | S1, S2, S3, S9 |
| Campaign must be active | R3.2-R3.4 | S1, S4, S5 |
| Tracking code must be unique | R2 | S7, S8 |
| Generated link returned in JSON 201 | R3.1, R7 | S1, S10 |
| Suspended Campaign cannot generate active link | R3.4 | S5 |

## 5. Explicit Exclusions

Public redirect endpoint, click recording, visit analytics, conversion attribution, IP address collection, user-agent collection, geolocation, link expiration, QR code generation, link rotation, link deactivation, `is_active` column, link deletion, multiple-link management, dashboards, AI features, frontend implementation, and Admin tracking-link management are not part of KAN-14.
