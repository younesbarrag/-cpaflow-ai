# Specification - KAN-13: Creer et gerer les campagnes CPA

## 1. Functional Requirements

### R1 - Persistence and Ownership

| ID | Requirement |
|---|---|
| R1.1 | A Campaign belongs to exactly one Offer through non-null `offer_id`. |
| R1.2 | Campaign ownership is derived from the parent Offer's `user_id`; Campaign has no direct `user_id`. |
| R1.3 | `offer_id` is set through the Offer relationship and is immutable after creation. |
| R1.4 | Deleting an Offer cascades to its Campaigns. |
| R1.5 | Campaign creation and all reads/mutations are isolated to the authenticated Offer owner. |

### R2 - Creation

| ID | Requirement |
|---|---|
| R2.1 | `POST /api/v1/campaigns` creates a campaign and returns `201`. |
| R2.2 | `offer_id`, `name`, `traffic_source`, and `budget` are required. |
| R2.3 | A syntactically valid missing Offer returns `404`. |
| R2.4 | An existing foreign Offer returns `403` before campaign field or archived-offer validation. |
| R2.5 | An owned archived Offer returns `422` with an `offer_id` error. |
| R2.6 | A new Campaign always persists with status `draft`. |
| R2.7 | Store does not accept client-selected status; submitting any `status`, including `draft`, returns `422`. |

### R3 - Validation

| ID | Requirement |
|---|---|
| R3.1 | Name is a trimmed, required string of at most 255 characters. |
| R3.2 | Traffic source is a trimmed, required free-form string of at most 255 characters. |
| R3.3 | Budget is numeric, non-negative, at most `9999999999.99`, and has at most two fractional digits. |
| R3.4 | Campaign status uses `CampaignStatus`: `draft`, `active`, or `suspended`. |
| R3.5 | Initial status is not user-submittable; `CreateCampaignAction` always sets `CampaignStatus::Draft` and dedicated endpoints perform later transitions. |
| R3.6 | Validation failures return `422` and do not persist changes. |

### R4 - Listing and Viewing

| ID | Requirement |
|---|---|
| R4.1 | `GET /api/v1/campaigns` returns only campaigns whose Offer belongs to the authenticated user. |
| R4.2 | Results are ordered by Campaign `id` descending and paginated exactly 15 per page. |
| R4.3 | Pagination preserves the existing `data`, `links`, and `meta` structure. |
| R4.4 | No campaign search or filter is introduced in KAN-13. |
| R4.5 | `GET /api/v1/campaigns/{campaign}` returns `200` to the owner, `403` for an existing foreign Campaign, and `404` when missing. |
| R4.6 | Listing explicitly eager-loads only Offer `id` and `name`; CampaignResource does not lazy-load Offer data. |

### R5 - Partial Update

| ID | Requirement |
|---|---|
| R5.1 | `PATCH /api/v1/campaigns/{campaign}` supports partial updates to name, traffic source, and budget. |
| R5.2 | Omitted editable fields remain unchanged. |
| R5.3 | An empty payload or payload with no editable field returns `422`. |
| R5.4 | `offer_id`, `user_id`, and `status` are prohibited and return `422` if submitted. |
| R5.5 | Update authorization executes before field validation, so an existing foreign Campaign returns `403` even with invalid data. |

### R6 - Lifecycle

| ID | Requirement |
|---|---|
| R6.1 | `POST /campaigns/{campaign}/activate` permits `draft -> active` and `suspended -> active`. |
| R6.2 | `POST /campaigns/{campaign}/suspend` permits only `active -> suspended`. |
| R6.3 | General PATCH cannot change status. |
| R6.4 | Invalid and repeated transitions return `409 Conflict`, are non-idempotent, and do not write. |
| R6.5 | Lifecycle Actions change only status and preserve all other Campaign fields. |

### R7 - Authorization

| ID | Requirement |
|---|---|
| R7.1 | All campaign routes use `auth:sanctum`; guests receive `401`. |
| R7.2 | `CampaignPolicy` authorizes view, update, activate, and suspend through `Campaign -> Offer -> User`. |
| R7.3 | `OfferPolicy::createCampaign` authorizes parent ownership during creation. |
| R7.4 | There is no Admin bypass. |

### R8 - Serialization

| ID | Requirement |
|---|---|
| R8.1 | CampaignResource returns id, minimal Offer context, name, traffic source, budget, status, and timestamps. |
| R8.2 | Offer context contains only `id` and `name`. |
| R8.3 | Budget is serialized as a fixed two-decimal string. |
| R8.4 | Dates follow OfferResource's current Carbon JSON serialization. |

## 2. Behavioral Scenarios

### S1 - Create under an owned Offer

**Given** an authenticated user owns a non-archived Offer  
**When** they submit valid `offer_id`, name, traffic source, and budget  
**Then** the API returns `201`, Campaign belongs to that Offer, status is `draft`, and all normalized values are persisted.

### S2 - Parent Offer errors and ordering

**Given** a syntactically valid Offer ID does not exist  
**When** an authenticated user creates a Campaign  
**Then** the API returns `404` and persists nothing.

**Given** an Offer exists but belongs to another user  
**When** the authenticated user submits it with otherwise invalid Campaign fields  
**Then** authorization runs first, the API returns `403`, and persists nothing.

**Given** an archived Offer belongs to the authenticated user  
**When** they create a Campaign  
**Then** the API returns `422` with an `offer_id` error and persists nothing.

### S3 - Creation validation

**Given** an owned eligible Offer  
**When** name or traffic source is absent, whitespace-only, not a string, or over 255 characters  
**Then** the API returns `422` and persists nothing.

**Given** an owned eligible Offer  
**When** budget is negative, over `9999999999.99`, or has over two fractional digits  
**Then** the API returns `422` and persists nothing.

**Given** an owned eligible Offer  
**When** any status is submitted, including `draft`, `active`, `suspended`, or an unknown value  
**Then** the API returns `422` and persists nothing.

### S4 - Isolated pagination

**Given** user A has 16 Campaigns across their Offers and user B has Campaigns  
**When** user A requests page 1  
**Then** `data` contains the newest 15 Campaigns owned through user A's Offers, foreign Campaigns are absent, and `meta` reports `per_page: 15` and user A's total.

**And** each Campaign's Offer `id` and `name` are explicitly eager-loaded before CampaignResource serialization, so listing does not depend on lazy loading or issue one Offer query per Campaign.

### S5 - Show authorization

**Given** a Campaign belongs through an Offer to the authenticated user  
**When** they request it  
**Then** the API returns `200` with CampaignResource.

**Given** an existing Campaign belongs to another user  
**When** the authenticated user requests it  
**Then** the API returns `403`.

**Given** a Campaign ID is missing  
**When** an authenticated user requests it  
**Then** route model binding returns `404`.

### S6 - PATCH semantics and protected fields

**Given** the owner submits only a valid new name  
**When** they PATCH the Campaign  
**Then** only name changes, other fields remain unchanged, and the API returns `200`.

**Given** the owner submits `{}` or only unknown fields  
**When** they PATCH the Campaign  
**Then** the API returns `422` and the Campaign remains unchanged.

**Given** the owner submits `offer_id`, `user_id`, or `status`  
**When** they PATCH the Campaign, alone or alongside a valid name, traffic source, or budget  
**Then** the API returns `422` and ownership/status remain unchanged.

**Given** a non-owner submits invalid PATCH data to an existing Campaign  
**When** the request is processed  
**Then** policy authorization returns `403` before validation and the Campaign remains unchanged.

### S7 - Activate and suspend

**Given** an owned draft Campaign  
**When** the owner activates it  
**Then** status becomes `active`, other fields remain unchanged, and the API returns `200`.

**Given** an owned active Campaign  
**When** the owner suspends it  
**Then** status becomes `suspended`, other fields remain unchanged, and the API returns `200`.

**Given** an owned suspended Campaign  
**When** the owner activates it  
**Then** status becomes `active` and the API returns `200`.

### S8 - Invalid and repeated transitions

**Given** an owned draft Campaign  
**When** the owner suspends it  
**Then** the API returns `409`, status remains `draft`, and `updated_at` is unchanged.

**Given** an owned active Campaign  
**When** the owner activates it again  
**Then** the API returns `409`, status remains `active`, and `updated_at` is unchanged.

**Given** an owned suspended Campaign  
**When** the owner suspends it again  
**Then** the API returns `409`, status remains `suspended`, and `updated_at` is unchanged.

### S9 - Lifecycle authorization

**Given** no user is authenticated  
**When** activate or suspend is requested  
**Then** Sanctum returns `401` and Campaign remains unchanged.

**Given** an existing Campaign belongs to another user  
**When** activate or suspend is requested  
**Then** policy authorization returns `403` and Campaign remains unchanged.

## 3. HTTP Contracts

### 3.1 Routes

| Method | Endpoint | Name | Success |
|---|---|---|---:|
| GET | `/api/v1/campaigns` | `api.v1.campaigns.index` | `200` |
| POST | `/api/v1/campaigns` | `api.v1.campaigns.store` | `201` |
| GET | `/api/v1/campaigns/{campaign}` | `api.v1.campaigns.show` | `200` |
| PATCH | `/api/v1/campaigns/{campaign}` | `api.v1.campaigns.update` | `200` |
| POST | `/api/v1/campaigns/{campaign}/activate` | `api.v1.campaigns.activate` | `200` |
| POST | `/api/v1/campaigns/{campaign}/suspend` | `api.v1.campaigns.suspend` | `200` |

### 3.2 Store request and response

```json
{
  "offer_id": 7,
  "name": "TikTok July",
  "traffic_source": "TikTok Ads",
  "budget": "1500.00"
}
```

```json
{
  "data": {
    "campaign": {
      "id": 42,
      "offer": { "id": 7, "name": "Fitness Offer" },
      "name": "TikTok July",
      "traffic_source": "TikTok Ads",
      "budget": "1500.00",
      "status": "draft",
      "created_at": "2026-07-23T12:00:00.000000Z",
      "updated_at": "2026-07-23T12:00:00.000000Z"
    }
  }
}
```

### 3.3 Collection response

```json
{
  "data": [],
  "links": {
    "first": "http://localhost/api/v1/campaigns?page=1",
    "last": "http://localhost/api/v1/campaigns?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

### 3.4 Error precedence

| Request condition | Required response |
|---|---:|
| Guest | `401` |
| Valid-shaped but missing parent/resource ID | `404` |
| Existing foreign parent/resource | `403` |
| Owned archived Offer on Store | `422` |
| Invalid field or protected PATCH field | `422` |
| Invalid/repeated lifecycle transition | `409` |

## 4. Acceptance Mapping

| Jira acceptance criterion | Requirements | Scenarios |
|---|---|---|
| Campaign belongs to an Offer owned by authenticated user | R1, R2.3-R2.5, R7 | S1, S2, S9 |
| Archived Offer cannot create Campaign | R2.5 | S2 |
| Name, traffic source, budget, and status validated | R2.6-R2.7, R3 | S3, S6, S8 |
| List, view, update, activate, suspend | R4-R6 | S4-S8 |
| Pagination and authenticated-user isolation | R4.1-R4.3 | S4 |

## 5. Explicit Exclusions

Tracking links, clicks, conversions, analytics, attribution, campaign deletion, campaign archival, AI features, dashboards, frontend implementation, and Admin campaign management are not part of KAN-13.
