# Spec: Create and List CPA Offers (KAN-11)

## Requirement 1: Authenticated User Can Create an Offer

### R1.1 — Offer creation succeeds

```
GIVEN   an authenticated user with a valid Sanctum token
AND     valid offer data: name, destination_url, payout, status
WHEN    POST /api/v1/offers is sent with valid data
THEN    HTTP 201 is returned
AND     response contains data.offer with id, name, destination_url, payout, status, description, created_at, updated_at
AND     database offers table contains a row with the submitted data
AND     the offer's user_id matches the authenticated user's id
```

### R1.2 — Offer is linked to authenticated user

```
GIVEN   an authenticated user with id=1
WHEN    POST /api/v1/offers is sent with valid data
THEN    database offers.user_id = 1
AND     the authenticated user cannot specify a different user_id
```

### R1.3 — Unauthenticated creation returns 401

```
GIVEN   no authentication token
WHEN    POST /api/v1/offers is sent
THEN    HTTP 401 is returned
```

## Requirement 2: Offer Input Is Validated

### R2.1 — Name is required

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent without name
THEN    HTTP 422 is returned
AND     response contains validation error for name
```

### R2.2 — Name max length

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent with name longer than 255 characters
THEN    HTTP 422 is returned
AND     response contains validation error for name
```

### R2.3 — Destination URL is required

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent without destination_url
THEN    HTTP 422 is returned
AND     response contains validation error for destination_url
```

### R2.4 — Destination URL must be valid

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent with destination_url = "not-a-url"
THEN    HTTP 422 is returned
AND     response contains validation error for destination_url
```

### R2.5 — Destination URL max length

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent with destination_url longer than 2048 characters
THEN    HTTP 422 is returned
AND     response contains validation error for destination_url
```

### R2.6 — Payout is required

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent without payout
THEN    HTTP 422 is returned
AND     response contains validation error for payout
```

### R2.7 — Payout cannot be negative

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent with payout = -1
THEN    HTTP 422 is returned
AND     response contains validation error for payout
```

### R2.8 — Payout precision is validated

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent with payout = "10.999"
THEN    HTTP 422 is returned
AND     response contains validation error for payout
```

### R2.9 — Status is required

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent without status
THEN    HTTP 422 is returned
AND     response contains validation error for status
```

### R2.10 — Status must be valid OfferStatus

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent with status = "invalid_status"
THEN    HTTP 422 is returned
AND     response contains validation error for status
```

### R2.11 — Description may be null

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent without description
THEN    HTTP 201 is returned
AND     database offers.description = null
```

### R2.12 — Description max length

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers is sent with description longer than 10000 characters
THEN    HTTP 422 is returned
AND     response contains validation error for description
```

## Requirement 3: Ownership Protection

### R3.1 — Public user_id is ignored

```
GIVEN   an authenticated user with id=1
WHEN    POST /api/v1/offers is sent with user_id = 999
THEN    HTTP 201 is returned
AND     database offers.user_id = 1 (not 999)
```

### R3.2 — Another user cannot be selected

```
GIVEN   an authenticated user with id=1
AND     another user with id=2
WHEN    POST /api/v1/offers is sent with user_id = 2
THEN    HTTP 201 is returned
AND     database offers.user_id = 1 (not 2)
```

## Requirement 4: Offer Response Structure

### R4.1 — Response uses OfferResource

```
GIVEN   an authenticated user
WHEN    POST /api/v1/offers succeeds
THEN    response contains data.offer object
AND     data.offer contains id (integer)
AND     data.offer contains name (string)
AND     data.offer contains destination_url (string)
AND     data.offer contains payout (string, decimal format)
AND     data.offer contains status (string)
AND     data.offer contains description (string or null)
AND     data.offer contains created_at (datetime)
AND     data.offer contains updated_at (datetime)
AND     data.offer does NOT contain user_id
AND     data.offer does NOT contain password or remember_token
```

### R4.2 — Status is serialized as enum string

```
GIVEN   an authenticated user creates an offer with status = "draft"
WHEN    the response is inspected
THEN    data.offer.status = "draft"
```

### R4.3 — Payout is serialized as decimal string

```
GIVEN   an authenticated user creates an offer with payout = "25.50"
WHEN    the response is inspected
THEN    data.offer.payout = "25.50"
AND     data.offer.payout is NOT a float (no scientific notation, no trailing zeros beyond 2)
```

## Requirement 5: Authenticated User Can List Their Offers

### R5.1 — List returns paginated offers

```
GIVEN   an authenticated user with 3 offers
WHEN    GET /api/v1/offers is sent
THEN    HTTP 200 is returned
AND     response contains data array with up to 15 offers
AND     response contains meta with current_page, last_page, per_page, total
AND     response contains links with first, last, prev, next
```

### R5.2 — List contains only authenticated user's offers

```
GIVEN   an authenticated user with 2 offers
AND     another user with 3 offers
WHEN    GET /api/v1/offers is sent by the first user
THEN    HTTP 200 is returned
AND     data contains exactly 2 offers
AND     none of the offers belong to the other user
```

### R5.3 — Ordering is deterministic

```
GIVEN   an authenticated user with multiple offers
WHEN    GET /api/v1/offers is sent
THEN    offers are ordered by id descending (newest first)
```

### R5.4 — Empty list returns valid pagination

```
GIVEN   an authenticated user with no offers
WHEN    GET /api/v1/offers is sent
THEN    HTTP 200 is returned
AND     data is an empty array
AND     meta.total = 0
AND     meta.current_page = 1
AND     meta.last_page = 1
```

### R5.5 — Unauthenticated list returns 401

```
GIVEN   no authentication token
WHEN    GET /api/v1/offers is sent
THEN    HTTP 401 is returned
```

## Requirement 6: Payout Precision

### R6.1 — Payout persists without corruption

```
GIVEN   an authenticated user creates an offer with payout = "0.10"
WHEN    the offer is retrieved from the database
THEN    offers.payout = "0.10"
AND     offers.payout is NOT "0.0999999999" or any float approximation
```

### R6.2 — Large payout values are accepted

```
GIVEN   an authenticated user creates an offer with payout = "9999999999.99"
WHEN    the offer is retrieved from the database
THEN    offers.payout = "9999999999.99"
```

## Requirement 7: OfferStatus Cast

### R7.1 — Status is cast to OfferStatus enum

```
GIVEN   an offer is loaded from the database
WHEN    offer->status is inspected
THEN    offer->status is an instance of App\Enums\OfferStatus
```

## Requirement 8: Relationship Integrity

### R8.1 — User::offers() returns HasMany

```
GIVEN   a User model instance
WHEN    user->offers is accessed
THEN    it returns an Eloquent HasMany relationship
AND     it can be chained with ->create(), ->where(), ->paginate()
```

### R8.2 — Offer::user() returns BelongsTo

```
GIVEN   an Offer model instance
WHEN    offer->user is accessed
THEN    it returns the related User model
```

### R8.3 — Deleting user cascades offer deletion

```
GIVEN   a user with 3 offers
WHEN    the user is deleted
THEN    all 3 offers are also deleted
```
