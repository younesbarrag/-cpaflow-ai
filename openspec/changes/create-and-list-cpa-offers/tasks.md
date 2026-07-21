# Tasks: Create and List CPA Offers (KAN-11)

## Section 1: Repository Verification

- [x] **1.1** Verify Git branch is `feature/KAN-11-offers-create-list`
- [x] **1.2** Verify Git working tree is clean
- [x] **1.3** Verify Laravel version: `Laravel Framework 13.20.0`
- [x] **1.4** Verify existing tests pass (`php artisan test`)
- [x] **1.5** Verify `OfferStatus` enum exists with Draft, Active, Suspended, Archived cases
- [x] **1.6** Verify User model has `HasApiTokens`, `#[Fillable]`, `UserRole` cast
- [x] **1.7** Verify `auth:sanctum` middleware is used in `routes/api.php`
- [x] **1.8** Verify API versioning under `/api/v1`
- [x] **1.9** Verify Pest with SQLite in-memory (`phpunit.xml`)

## Section 2: Database Migration

- [x] **2.1** Create `database/migrations/2026_07_21_000000_create_offers_table.php`
- [x] **2.2** Table includes: `id`, `user_id`, `name`, `destination_url`, `payout`, `status`, `description`, `timestamps`
- [x] **2.3** `user_id` is `foreignId` with `constrained()` and `cascadeOnDelete()`
- [x] **2.4** `name` is `string('name', 255)` NOT NULL
- [x] **2.5** `destination_url` is `string('destination_url', 2048)` NOT NULL
- [x] **2.6** `payout` is `decimal('payout', 12, 2)` NOT NULL DEFAULT 0.00
- [x] **2.7** `status` is `string('status', 20)` NOT NULL DEFAULT 'draft'
- [x] **2.8** `description` is `text()->nullable()`
- [x] **2.9** Composite index on `user_id` + `status` (`$table->index(['user_id', 'status'])`)
- [x] **2.10** No standalone `user_id` index — FK constraint already creates one
- [x] **2.11** No native ENUM used — uses string column with enum validation
- [x] **2.12** Run `php artisan migrate` on MySQL
- [x] **2.13** Verify migration status with `php artisan migrate:status`

## Section 3: Offer Model

- [x] **3.1** Create `app/Models/Offer.php`
- [x] **3.2** `use HasFactory` trait
- [x] **3.3** `$fillable` includes: `name`, `destination_url`, `payout`, `status`, `description`
- [x] **3.4** `user_id` is NOT in `$fillable`
- [x] **3.5** `payout` cast to `decimal:2` (string, never float)
- [x] **3.6** `status` cast to `OfferStatus::class`
- [x] **3.7** `user()` returns `BelongsTo(User::class)`
- [x] **3.8** No model events or boot method

## Section 4: User Model Extension

- [x] **4.1** Add `offers(): HasMany` method to `app/Models/User.php`
- [x] **4.2** Add `use Illuminate\Database\Eloquent\Relations\HasMany` import
- [x] **4.3** Add `use App\Models\Offer` import (not needed — uses string class reference)

## Section 5: Factory

- [x] **5.1** Create `database/factories/OfferFactory.php`
- [x] **5.2** Factory creates a related `User` by default
- [x] **5.3** Generates valid HTTPS destination URLs
- [x] **5.4** Generates payout values compatible with `DECIMAL(12,2)`
- [x] **5.5** Generates valid `OfferStatus` values
- [x] **5.6** Provides `draft()` state method
- [x] **5.7** Provides `active()` state method
- [x] **5.8** Provides `forUser(User $user)` state method

## Section 6: Form Request

- [x] **6.1** Create `app/Http/Requests/Api/V1/Offer/StoreOfferRequest.php`
- [x] **6.2** `authorize()` returns true (auth:sanctum handles authentication)
- [x] **6.3** `name`: required, string, max 255
- [x] **6.4** `destination_url`: required, string, url:http,https, max 2048
- [x] **6.5** `payout`: required, numeric, min 0, max 9999999999.99, decimal:0,2
- [x] **6.6** `status`: required, `Rule::enum(OfferStatus::class)`
- [x] **6.7** `description`: nullable, string, max 10000
- [x] **6.8** `user_id` is NOT validated or accepted
- [x] **6.9** `prepareForValidation()` trims name, destination_url, description

## Section 7: CreateOfferAction

- [x] **7.1** Create `app/Actions/Offer/CreateOfferAction.php`
- [x] **7.2** `execute(User $user, string $name, string $destinationUrl, string $payout, OfferStatus $status, ?string $description): Offer`
- [x] **7.3** Creates offer through `$user->offers()->create([...])`
- [x] **7.4** Returns the created `Offer` instance
- [x] **7.5** Does NOT return HTTP responses
- [x] **7.6** Does NOT accept `user_id` or arbitrary arrays

## Section 8: OfferResource

- [x] **8.1** Create `app/Http/Resources/Api/V1/OfferResource.php`
- [x] **8.2** Exposes: `id`, `name`, `destination_url`, `payout`, `status`, `description`, `created_at`, `updated_at`
- [x] **8.3** `status` serialized as `$this->status->value` (string)
- [x] **8.4** `payout` serialized as decimal string (via model cast)
- [x] **8.5** Does NOT expose `user_id`

## Section 9: OfferController

- [x] **9.1** Create `app/Http/Controllers/Api/V1/OfferController.php`
- [x] **9.2** `store()` receives `StoreOfferRequest`, delegates to `CreateOfferAction`
- [x] **9.3** `store()` returns HTTP 201 with `OfferResource`
- [x] **9.4** `index()` queries `$request->user()->offers()`
- [x] **9.5** `index()` orders by `id DESC` (newest first)
- [x] **9.6** `index()` paginates with default 15 per page
- [x] **9.7** `index()` returns `OfferResource::collection` with `data`, `links`, `meta`
- [x] **9.8** Controller is thin — no business logic beyond query and response

## Section 10: API Routes

- [x] **10.1** Add `GET /api/v1/offers` route → `OfferController::index`
- [x] **10.2** Add `POST /api/v1/offers` route → `OfferController::store`
- [x] **10.3** Both routes inside `auth:sanctum` middleware group
- [x] **10.4** Route names: `api.v1.offers.index`, `api.v1.offers.store`
- [x] **10.5** No duplicate routes

## Section 11: Tests

- [x] **11.1** Create `tests/Feature/Api/V1/OfferApiTest.php`
- [x] **11.2** Test: guest cannot list offers → 401
- [x] **11.3** Test: guest cannot create offer → 401
- [x] **11.4** Test: authenticated user creates offer → 201
- [x] **11.5** Test: offer belongs to authenticated user (DB assert)
- [x] **11.6** Test: response uses OfferResource (assertJsonStructure)
- [x] **11.7** Test: name is required → 422
- [x] **11.8** Test: destination_url is required → 422
- [x] **11.9** Test: destination_url must be valid URL → 422
- [x] **11.10** Test: payout is required → 422
- [x] **11.11** Test: payout cannot be negative → 422
- [x] **11.12** Test: payout precision is validated (decimal:0,2) → 422
- [x] **11.13** Test: oversized payout rejected → 422
- [x] **11.14** Test: status is validated through OfferStatus enum → 422
- [x] **11.15** Test: description may be null
- [x] **11.16** Test: public user_id is ignored (ownership protection)
- [x] **11.17** Test: another user cannot be selected as owner
- [x] **11.18** Test: status is cast to OfferStatus in model
- [x] **11.19** Test: payout persists without floating-point corruption
- [x] **11.20** Test: list is paginated
- [x] **11.21** Test: list contains only authenticated user's offers
- [x] **11.22** Test: another user's offers are excluded
- [x] **11.23** Test: ordering is deterministic (newest first)
- [x] **11.24** Test: empty list returns valid pagination metadata
- [x] **11.25** Test: resource fields are correct
- [x] **11.26** Test: sensitive fields absent (user_id not exposed)

## Section 12: Regression

- [x] **12.1** Run full test suite — all tests pass (134/134)
- [x] **12.2** Verify KAN-8 health tests still pass
- [x] **12.3** Verify KAN-9 authentication tests still pass
- [x] **12.4** Verify KAN-10 profile and admin middleware tests still pass

## Section 13: Code Quality

- [x] **13.1** Run `./vendor/bin/pint --test` — code style compliant
- [x] **13.2** Run `npm run build` — frontend builds successfully
- [x] **13.3** Verify no sensitive data in git diff

## Section 14: Documentation Update

- [x] **14.1** Update `docs/conception-technique.md` — offers table: `Planifié` → `Implémenté`
- [x] **14.2** Update MLD offers table: `destination_url` to `VARCHAR(2048)`, `payout` to `DECIMAL(12,2)`
- [x] **14.3** Update User → Offer relationship status
- [x] **14.4** Update OfferStatus usage status
- [x] **14.5** Update project current-state: add offers table, model, API endpoints
- [x] **14.6** Update routes list: add `GET /api/v1/offers` and `POST /api/v1/offers`

## Section 15: Final Verification

- [x] **15.1** Verify Git status: only KAN-11 files changed
- [x] **15.2** Verify no database migration was rolled back or reset
- [x] **15.3** Verify no Composer or npm dependency changes
- [x] **15.4** Verify route list: `GET /api/v1/offers` and `POST /api/v1/offers` exist
