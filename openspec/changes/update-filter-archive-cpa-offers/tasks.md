# Tasks — KAN-12: Modifier, filtrer et archiver ses offres CPA

## Task Order

Tasks are ordered by dependency. Each task must be completed before the next can begin.

---

### Phase 1: Authorization Foundation

- [x] **T1.1** Create `app/Policies/OfferPolicy.php`
  - `update(User $user, Offer $offer): bool` — returns `$user->id === $offer->user_id`
  - `archive(User $user, Offer $offer): bool` — returns `$user->id === $offer->user_id`
  - Verify: Laravel 13 auto-discovers policies without explicit registration

### Phase 2: Actions

- [x] **T2.1** Create `app/Actions/Offer/UpdateOfferAction.php`
  - Method: `execute(Offer $offer, array $fields): Offer`
  - Calls `$offer->update($fields)`
  - Returns `$offer->fresh()`
  - No authorization, no HTTP responses

- [x] **T2.2** Create `app/Actions/Offer/ArchiveOfferAction.php`
  - Method: `execute(Offer $offer): Offer`
  - Checks `$offer->status !== OfferStatus::Archived` before update
  - Sets `status` to `OfferStatus::Archived`
  - Returns `$offer->fresh()`
  - No authorization, no HTTP responses

### Phase 3: Form Requests

- [x] **T3.1** Create `app/Http/Requests/Api/V1/Offer/UpdateOfferRequest.php`
  - `authorize()` returns `true`
  - `prepareForValidation()`: trim name, destination_url, description
  - Rules: all fields `sometimes`, same validation as StoreOfferRequest where applicable
  - Ownership fields (user_id, owner_id, affiliate_id) not in rules

- [x] **T3.2** Create `app/Http/Requests/Api/V1/Offer/IndexOfferRequest.php`
  - `authorize()` returns `true`
  - `prepareForValidation()`: trim search whitespace
  - Rules: `status` nullable + enum, `search` nullable + string + max:255

### Phase 4: Model Scopes

- [x] **T4.1** Add `scopeStatus(Builder $query, OfferStatus $status): Builder` to `app/Models/Offer.php`
  - `return $query->where('status', $status)`

- [x] **T4.2** Add `scopeSearch(Builder $query, ?string $search): Builder` to `app/Models/Offer.php`
  - Return unchanged query if search is null/empty
  - Otherwise `where('name', 'like', '%' . $search . '%')`

### Phase 5: Controller

- [x] **T5.1** Add `update()` method to `app/Http/Controllers/Api/V1/OfferController.php`
  - Parameters: `UpdateOfferRequest $request, Offer $offer, UpdateOfferAction $action`
  - Call `$this->authorize('update', $offer)`
  - Call `$action->execute($offer, $request->validated())`
  - Return JSON with OfferResource, HTTP 200

- [x] **T5.2** Add `archive()` method to `app/Http/Controllers/Api/V1/OfferController.php`
  - Parameters: `Request $request, Offer $offer, ArchiveOfferAction $action`
  - Call `$this->authorize('archive', $offer)`
  - Call `$action->execute($offer)`
  - Return JSON with OfferResource, HTTP 200

- [x] **T5.3** Extend `index()` method in `app/Http/Controllers/Api/V1/OfferController.php`
  - Accept `IndexOfferRequest $request` instead of `Request $request`
  - Chain `->when()` for status and search scopes
  - Preserve existing pagination (15), ordering (id desc), and ownership scope

### Phase 6: Routes

- [x] **T6.1** Add `PATCH /api/v1/offers/{offer}` route to `routes/api.php`
  - Name: `api.v1.offers.update`
  - Within `auth:sanctum` middleware group

- [x] **T6.2** Add `POST /api/v1/offers/{offer}/archive` route to `routes/api.php`
  - Name: `api.v1.offers.archive`
  - Within `auth:sanctum` middleware group

### Phase 7: Tests

- [x] **T7.1** Create `tests/Feature/Api/V1/OfferManagementApiTest.php` — Update Authorization
  - Guest cannot update (401)
  - Owner can update (200)
  - Non-owner receives 403
  - user_id input cannot transfer ownership
  - Owner relationship remains unchanged after update

- [x] **T7.2** Tests — Update Validation
  - Partial name update works
  - Partial payout update works
  - Valid HTTP/HTTPS URL accepted
  - Invalid protocol returns 422
  - Negative payout returns 422
  - Excessive decimal precision returns 422
  - Oversized payout returns 422
  - Invalid status returns 422
  - Nullable description works
  - Name trimming on update
  - URL trimming on update

- [x] **T7.3** Tests — Archive
  - Owner archives offer (200)
  - Status becomes archived
  - Other fields remain unchanged
  - Non-owner receives 403
  - Guest receives 401
  - Repeated archive is idempotent (200)

- [x] **T7.4** Tests — Filtering
  - Status filter returns only matching status
  - Invalid status returns 422
  - Search returns matching names
  - Search excludes non-matching names
  - Combined status + search works
  - Filters never expose another user's offers
  - Pagination remains valid
  - Deterministic ordering remains correct

- [x] **T7.5** Tests — Policy
  - Owner allowed
  - Non-owner denied
  - Existing foreign offer returns 403 (not 404)

- [x] **T7.6** Tests — Regression
  - KAN-11 creation tests still pass
  - KAN-11 listing tests still pass
  - No migration or dependency regression

### Phase 8: Documentation

- [x] **T8.1** Update `docs/conception-technique.md`
  - Mark update/archive/filter as implemented (KAN-12)
  - Update routes list with new endpoints
  - Update API section

## Verification Checklist

After all tasks:

- [x] `php artisan test` — all tests pass
- [x] `./vendor/bin/pint --test` — code style compliant
- [x] `php artisan route:list --path=api/v1` — routes registered
- [x] No new migrations created
- [x] No new packages installed
- [x] Git status shows only `openspec/changes/update-filter-archive-cpa-offers/` as new
