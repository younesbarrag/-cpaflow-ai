# Specification - KAN-15: Enregistrer un clic et rediriger vers l'offre

## 1. Functional Requirements

### R1 - Public Redirect

| ID | Requirement |
|---|---|
| R1.1 | `GET /t/{code}` resolves a TrackingLink by its unique code. |
| R1.2 | The route is public and requires no authentication. |
| R1.3 | The route is registered in `routes/web.php`, not under `/api/v1`. |
| R1.4 | The route name is `tracking.redirect`. |
| R1.5 | An unknown code returns `404 Not Found`. |
| R1.6 | A valid code with an active Campaign redirects to Offer.destination_url. |

### R2 - Campaign Status Verification

| ID | Requirement |
|---|---|
| R2.1 | A TrackingLink linked to an active Campaign proceeds with redirect. |
| R2.2 | A TrackingLink linked to a draft Campaign returns `404`. |
| R2.3 | A TrackingLink linked to a suspended Campaign returns `404`. |
| R2.4 | The `404` response for inactive campaigns is identical to an unknown code. |
| R2.5 | No information about Campaign existence or status is leaked. |

### R3 - Click Recording

| ID | Requirement |
|---|---|
| R3.1 | A valid redirect records one TrackingClick row. |
| R3.2 | The click belongs to the resolved TrackingLink. |
| R3.3 | `created_at` is the authoritative click timestamp (synchronous recording). |
| R3.4 | Click persistence failure does not block the redirect. |
| R3.5 | Click persistence failure is logged via `report()`. |
| R3.6 | No database error details are exposed to the visitor. |

### R4 - IP Privacy

| ID | Requirement |
|---|---|
| R4.1 | Raw IP addresses are never stored in the database. |
| R4.2 | IP addresses are hashed using HMAC-SHA256 with a purpose-separated key derived from `APP_KEY`. |
| R4.3 | Both IPv4 and IPv6 are normalized using `inet_pton()` followed by `inet_ntop()`. |
| R4.4 | IPv6 zone identifiers are stripped before normalization. |
| R4.5 | `ip_hash` is nullable — missing or invalid IP results in null. |
| R4.6 | The same canonical IP produces the same hash. |
| R4.7 | Equivalent IPv6 textual forms produce the same hash. |
| R4.8 | Different canonical IPs produce different hashes. |
| R4.9 | The `ip_hash` value is never exposed in HTTP responses. |
| R4.10 | Rotating `APP_KEY` changes future hashes; this is acceptable because KAN-15 does not implement unique-visitor attribution. |

### R5 - Request Metadata

| ID | Requirement |
|---|---|
| R5.1 | User-Agent is read from the request header. |
| R5.2 | Referer is read from the request header. |
| R5.3 | UTM parameters are read from the query string. |
| R5.4 | All metadata fields are nullable. |
| R5.5 | Values are trimmed and truncated using multibyte-safe `mb_substr` to column max lengths. |
| R5.6 | Empty strings after trimming are stored as null. |
| R5.7 | Oversized metadata is truncated, not rejected, to preserve redirect reliability. |

### R6 - Destination URL Safety

| ID | Requirement |
|---|---|
| R6.1 | `filter_var(FILTER_VALIDATE_URL)` must succeed for the destination URL. |
| R6.2 | Scheme must be exactly `http` or `https` (case-insensitive). |
| R6.3 | Host must exist and must not be empty. |
| R6.4 | A URL failing any safety check returns `404`. |
| R6.5 | The safety check is defense-in-depth — Offer validation already restricts schemes at creation. |

### R7 - Redirect Response

| ID | Requirement |
|---|---|
| R7.1 | Successful redirect uses HTTP `302 Found`. |
| R7.2 | The redirect location is the Offer.destination_url. |
| R7.3 | The destination is resolved through `TrackingLink → Campaign → Offer`. |

### R8 - Relationships

| ID | Requirement |
|---|---|
| R8.1 | TrackingLink has many TrackingClicks (`TrackingLink::clicks()`). |
| R8.2 | TrackingClick belongs to one TrackingLink (`TrackingClick::trackingLink()`). |
| R8.3 | Deleting a TrackingLink cascades to its TrackingClicks. |
| R8.4 | Campaign → Offer relationships are loaded with bounded eager-loading queries. |

### R9 - Missing Relations

| ID | Requirement |
|---|---|
| R9.1 | A TrackingLink with a null Campaign returns `404`. |
| R9.2 | A TrackingLink with a null Offer returns `404`. |
| R9.3 | No property-access exception is produced for missing relations. |
| R9.4 | No click is recorded for missing relations. |

## 2. Behavioral Scenarios

### S1 - Valid code with active Campaign

**Given** a TrackingLink exists with a known code, its Campaign is active, and its Offer has a valid destination URL
**When** a visitor opens `GET /t/{code}`
**Then** one TrackingClick is persisted with the correct `tracking_link_id`, `created_at` is set, and the visitor receives `302` redirect to Offer.destination_url.

### S2 - Valid code redirects to Offer.destination_url

**Given** a TrackingLink exists and its Campaign's Offer has `destination_url = "https://example.com/offer"`
**When** a visitor opens `GET /t/{code}`
**Then** the redirect Location header is `https://example.com/offer`.

### S3 - Redirect uses 302

**Given** a valid code with an active Campaign
**When** a visitor opens `GET /t/{code}`
**Then** the HTTP status is `302 Found`.

### S4 - created_at represents click time

**Given** a valid code with an active Campaign
**When** a visitor opens `GET /t/{code}`
**Then** the persisted TrackingClick `created_at` is approximately the current time and represents the click event.

### S5 - Click belongs to expected TrackingLink

**Given** two TrackingLinks exist with different codes
**When** a visitor clicks the first code
**Then** the persisted TrackingClick has `tracking_link_id` matching the first TrackingLink, not the second.

### S6 - Unknown code returns 404

**Given** no TrackingLink exists with code `"nonexistent123"`
**When** a visitor opens `GET /t/nonexistent123`
**Then** the response is `404 Not Found` and no TrackingClick is created.

### S7 - Draft Campaign does not redirect

**Given** a TrackingLink exists and its Campaign is `draft`
**When** a visitor opens `GET /t/{code}`
**Then** the response is `404 Not Found` and no TrackingClick is created.

### S8 - Suspended Campaign does not redirect

**Given** a TrackingLink exists and its Campaign is `suspended`
**When** a visitor opens `GET /t/{code}`
**Then** the response is `404 Not Found` and no TrackingClick is created.

### S9 - Inactive Campaign behavior does not reveal state

**Given** one TrackingLink exists with a draft Campaign
**When** a visitor opens `GET /t/{code}`
**Then** the response status and body are identical to an unknown code `404`.

### S10 - No authentication required

**Given** no user is authenticated
**When** a visitor opens `GET /t/{code}` for a valid active link
**Then** the redirect proceeds without authentication.

### S11 - Referer normalization and storage

**Given** a valid code with an active Campaign
**When** a visitor opens `GET /t/{code}` with header `Referer: https://example.com/page`
**Then** the persisted TrackingClick has `referer = "https://example.com/page"`.

### S12 - User-Agent normalization and storage

**Given** a valid code with an active Campaign
**When** a visitor opens `GET /t/{code}` with header `User-Agent: Mozilla/5.0`
**Then** the persisted TrackingClick has `user_agent = "Mozilla/5.0"`.

### S13 - UTM normalization and storage

**Given** a valid code with an active Campaign
**When** a visitor opens `GET /t/{code}?utm_source=facebook&utm_medium=cpc&utm_campaign=spring&utm_term=shoes&utm_content=banner`
**Then** the persisted TrackingClick has the corresponding UTM fields populated.

### S14 - Empty metadata becomes null

**Given** a valid code with an active Campaign
**When** a visitor opens `GET /t/{code}` with empty User-Agent header
**Then** the persisted TrackingClick has `user_agent = null`.

### S15 - Oversized metadata is truncated safely

**Given** a valid code with an active Campaign
**When** a visitor sends a User-Agent header exceeding 512 characters
**Then** the persisted `user_agent` is truncated to 512 characters and the redirect proceeds.

### S16 - Raw IP is never persisted

**Given** a valid code with an active Campaign
**When** a visitor opens `GET /t/{code}`
**Then** no TrackingClick row contains the raw IP address in any field.

### S17 - Same canonical IP produces same hash

**Given** two clicks from the same normalized IP
**When** both are recorded
**Then** their `ip_hash` values are identical.

### S18 - Equivalent IPv6 forms produce same hash

**Given** one click from `2001:db8::1` and another from `2001:0db8:0000:0000:0000:0000:0000:0001`
**When** both are recorded
**Then** their `ip_hash` values are identical.

### S19 - Different canonical IPs produce different hashes

**Given** two clicks from different IP addresses
**When** both are recorded
**Then** their `ip_hash` values are different.

### S20 - Missing or invalid IP produces null

**Given** a valid code with an active Campaign
**When** `$request->ip()` returns null or an invalid IP string
**Then** the persisted TrackingClick has `ip_hash = null` and the redirect proceeds.

### S21 - Click persistence exception is reported

**Given** a valid code with an active Campaign
**When** the database INSERT for TrackingClick fails
**Then** the exception is passed to `report()`.

### S22 - Click persistence exception does not block redirect

**Given** a valid code with an active Campaign
**When** the database INSERT for TrackingClick fails
**Then** the visitor still receives `302` redirect to Offer.destination_url.

### S23 - Persistence exception details are not exposed

**Given** a valid code with an active Campaign
**When** the database INSERT for TrackingClick fails
**Then** the visitor receives only the `302` redirect, with no error message, stack trace, or database detail.

### S24 - Unsafe scheme returns 404

**Given** a TrackingLink exists and its Offer has `destination_url = "javascript:alert(1)"`
**When** a visitor opens `GET /t/{code}`
**Then** the response is `404 Not Found` and no redirect occurs.

### S25 - Malformed URL returns 404

**Given** a TrackingLink exists and its Offer has a malformed `destination_url`
**When** a visitor opens `GET /t/{code}`
**Then** the response is `404 Not Found`.

### S26 - URL without a host returns 404

**Given** a TrackingLink exists and its Offer has `destination_url = "http://"`
**When** a visitor opens `GET /t/{code}`
**Then** the response is `404 Not Found`.

### S27 - Missing Campaign relation returns 404

**Given** a TrackingLink exists but its Campaign has been deleted (simulated with null relationship)
**When** a visitor opens `GET /t/{code}`
**Then** the response is `404 Not Found` and no TrackingClick is created.

### S28 - Missing Offer relation returns 404

**Given** a TrackingLink exists, its Campaign exists, but the Campaign's Offer has been deleted (simulated with null relationship)
**When** a visitor opens `GET /t/{code}`
**Then** the response is `404 Not Found` and no TrackingClick is created.

### S29 - TrackingLink::clicks() works

**Given** a TrackingLink with 3 persisted TrackingClicks
**When** `$trackingLink->clicks` is accessed
**Then** it returns exactly 3 TrackingClick models.

### S30 - TrackingClick::trackingLink() works

**Given** a TrackingClick persisted for a specific TrackingLink
**When** `$trackingClick->trackingLink` is accessed
**Then** it returns the correct TrackingLink model.

### S31 - Cascade deletion works

**Given** a TrackingLink with persisted TrackingClicks
**When** the TrackingLink is deleted
**Then** all its TrackingClicks are also deleted.

### S32 - Required relationships are loaded with bounded queries

**Given** a valid code with an active Campaign
**When** the controller resolves the TrackingLink
**Then** the Campaign and Offer are loaded through bounded eager-loading queries, not through per-record lazy loading.

### S33 - KAN-14 generation behavior remains unaffected

**Given** the existing KAN-14 tracking link generation endpoint
**When** an owner generates a tracking link for an active Campaign
**Then** the behavior, response format, and database state are unchanged.

### S34 - Postman/Newman validates the real public flow

**Given** a Postman Collection v2.1 with the KAN-15 redirect tests
**When** the collection is run against a local environment
**Then** all tests pass: active redirect, click persisted, unknown code 404, draft 404, suspended 404, unsafe destination 404, no auth, UTM flow, KAN-14 link works.

## 3. HTTP Contracts

### 3.1 Route

| Method | Endpoint | Name | Success |
|---|---|---|---:|
| GET | `/t/{code}` | `tracking.redirect` | `302` |

### 3.2 Request

No body parameters. The code is a route parameter. UTM values are optional query parameters.

### 3.3 Success response

HTTP `302 Found` with `Location` header pointing to Offer.destination_url.

```
HTTP/1.1 302 Found
Location: https://example.com/offer
```

No response body is expected (browsers follow the redirect).

### 3.4 Error responses

#### Unknown code (404)

```
HTTP/1.1 404 Not Found
```

Standard Laravel 404 response. No distinction between unknown code, draft Campaign, suspended Campaign, unsafe URL, or missing relation.

#### Inactive Campaign (404)

Identical to unknown code — `404 Not Found`.

#### Unsafe destination URL (404)

Identical to unknown code — `404 Not Found`.

#### Missing Campaign or Offer relation (404)

Identical to unknown code — `404 Not Found`.

### 3.5 Response precedence

| Condition | Response |
|---|---:|
| Unknown code | `404` |
| Valid code, draft Campaign | `404` |
| Valid code, suspended Campaign | `404` |
| Valid code, missing Campaign relation | `404` |
| Valid code, missing Offer relation | `404` |
| Valid code, active Campaign, unsafe URL | `404` |
| Valid code, active Campaign, safe URL | `302` |
| Valid code, active Campaign, click persistence fails | `302` (with logged exception) |

## 4. Acceptance Mapping

| Jira acceptance criterion | Requirements | Scenarios |
|---|---|---|
| Valid TrackingLink with active Campaign records click and redirects | R1, R2, R3, R7 | S1, S2, S3, S4, S5 |
| Unknown code returns 404 | R1.5 | S6 |
| Draft or suspended Campaign must not redirect | R2.2, R2.3, R2.4 | S7, S8, S9 |
| Click recording failure must not block valid redirect | R3.4, R3.5, R3.6 | S21, S22, S23 |
| No authentication required | R1.2 | S10 |
| No raw sensitive visitor data stored | R4.1 | S16 |
| Tests verify persisted click data and redirect behavior | R3, R7 | S1-S5, S11-S34 |

## 5. Explicit Exclusions

Conversion attribution, revenue calculations, dashboard analytics, unique visitor counting, bot detection, geolocation, device fingerprinting, cookies, link expiration, link deactivation, QR codes, campaign frontend, admin click management, AI features, batch analytics, retention policies, queue-based click recording, Form Request validation, Policy authorization, and API Resource serialization are not part of KAN-15.
