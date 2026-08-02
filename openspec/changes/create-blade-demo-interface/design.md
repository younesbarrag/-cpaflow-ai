# Design - KAN-31: Create Blade Demo Interface

## 1. Existing Conventions Inspected

The design follows these implemented conventions:

- `routes/web.php`: Breeze web routes, `auth` middleware group, named routes, `/t/{code}` public route.
- `routes/auth.php`: Full Breeze auth routes — login, register, forgot-password, reset-password, verify-email, confirm-password, logout.
- `layouts/app.blade.php`: `<x-app-layout>` component with `$header` slot and `$slot`.
- `layouts/guest.blade.php`: `<x-guest-layout>` centered card layout.
- Existing components: `x-nav-link`, `x-responsive-nav-link`, `x-dropdown`, `x-dropdown-link`, `x-primary-button`, `x-secondary-button`, `x-danger-button`, `x-text-input`, `x-input-label`, `x-input-error`, `x-modal`, `x-application-logo`, `x-auth-session-status`.
- Actions pattern: `execute()` method, no HTTP awareness, receives Models and primitives.
- Policies: ownership-based authorization, `$user->can()` / `Gate::authorize()`.
- Form Requests: `authorize()`, `rules()`, `after()`, `prepareForValidation()`.
- Offer model: `name`, `destination_url`, `payout` (decimal:2), `status` (OfferStatus enum), `description`, `user()` BelongsTo, `campaigns()` HasMany.
- Campaign model: `name`, `traffic_source`, `budget` (decimal:2), `status` (CampaignStatus enum), `offer()` BelongsTo, `trackingLinks()` HasMany.
- TrackingLink model: `code`, `campaign()` BelongsTo, `clicks()` HasMany.
- Pest feature tests: `RefreshDatabase`, `actingAs()`, `assertDatabaseHas/Count/Missing`.

## 2. Design System

### 2.1 Design Tokens

All tokens are defined in `tailwind.config.js` under `theme.extend`.

```js
theme: {
    extend: {
        fontFamily: {
            sans: ['Figtree', ...defaultTheme.fontFamily.sans],
        },
        colors: {
            brand: {
                50:  '#eef2ff',
                100: '#e0e7ff',
                200: '#c7d2fe',
                300: '#a5b4fc',
                400: '#818cf8',
                500: '#6366f1',
                600: '#4f46e5',
                700: '#4338ca',
                800: '#3730a3',
                900: '#312e81',
            },
        },
        boxShadow: {
            'card': '0 1px 3px 0 rgb(0 0 0 / 0.04), 0 1px 2px -1px rgb(0 0 0 / 0.04)',
            'card-hover': '0 4px 6px -1px rgb(0 0 0 / 0.06), 0 2px 4px -2px rgb(0 0 0 / 0.06)',
        },
    },
},
```

### 2.2 Typography Scale

| Token | Tailwind Class | Usage |
|-------|---------------|-------|
| Page title | `text-2xl font-semibold text-gray-900` | Page headings |
| Section title | `text-lg font-semibold text-gray-900` | Card/section headings |
| Body | `text-sm text-gray-700` | Default body text |
| Small/label | `text-xs text-gray-500` | Labels, metadata, timestamps |
| Badge text | `text-xs font-medium` | Status badges |
| Navigation | `text-sm font-medium` | Nav links |

### 2.3 Spacing

| Token | Value | Usage |
|-------|-------|-------|
| Page padding | `py-8 px-4 sm:px-6 lg:px-8` | Main content area |
| Card padding | `p-6` | Card inner spacing |
| Card gap | `space-y-6` | Between cards on a page |
| Form gap | `space-y-4` | Between form fields |
| Inline gap | `gap-3` | Buttons in a row, badge groups |

### 2.4 Surface Hierarchy

| Surface | Classes | Usage |
|---------|---------|-------|
| Page background | `bg-gray-50` | Body behind content |
| Card | `bg-white shadow-card rounded-lg` | Content containers |
| Card elevated | `bg-white shadow-card-hover rounded-lg` | Hovered or focused cards |
| Sidebar | `bg-white border-r border-gray-200` | Side navigation |
| Top bar | `bg-white border-b border-gray-200` | Top navigation bar |
| Input | `border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm` | Form inputs |
| Modal backdrop | `bg-gray-500/75` | Modal overlay |
| Modal panel | `bg-white rounded-lg shadow-xl` | Modal content |

### 2.5 Border Radius

| Element | Radius | Classes |
|---------|--------|---------|
| Cards | 0.5rem | `rounded-lg` |
| Buttons | 0.375rem | `rounded-md` |
| Inputs | 0.375rem | `rounded-md` |
| Badges | 9999px | `rounded-full` |
| Modal | 0.5rem | `rounded-lg` |
| Avatar | 9999px | `rounded-full` |

### 2.6 Semantic Colors

| Semantic | Light | Dark | Tailwind |
|----------|-------|------|----------|
| Success | #059669 | #047857 | `text-emerald-600`, `bg-emerald-50`, `border-emerald-200` |
| Warning | #d97706 | #b45309 | `text-amber-600`, `bg-amber-50`, `border-amber-200` |
| Danger | #dc2626 | #b91c1c | `text-red-600`, `bg-red-50`, `border-red-200` |
| Info | #2563eb | #1d4ed8 | `text-blue-600`, `bg-blue-50`, `border-blue-200` |
| Brand | #4f46e5 | #4338ca | `text-brand-600`, `bg-brand-50`, `border-brand-200` |
| Neutral | #6b7280 | #4b5563 | `text-gray-500`, `bg-gray-50`, `border-gray-200` |

### 2.7 Status Badges

| Status | Background | Text | Dot |
|--------|------------|------|-----|
| Draft | `bg-gray-100` | `text-gray-700` | `bg-gray-400` |
| Active | `bg-emerald-100` | `text-emerald-700` | `bg-emerald-500` |
| Suspended | `bg-amber-100` | `text-amber-700` | `bg-amber-500` |
| Archived | `bg-red-100` | `text-red-700` | `bg-red-400` |

Badge component: `inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium`.

### 2.8 Buttons

| Variant | Classes | Usage |
|---------|---------|-------|
| Primary | `bg-brand-600 text-white hover:bg-brand-700 focus:ring-brand-500 rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition` | Main actions |
| Secondary | `bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-brand-500 rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition` | Secondary actions |
| Danger | `bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition` | Destructive actions |
| Ghost | `text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md px-3 py-1.5 text-sm font-medium transition` | Inline actions |
| Link | `text-brand-600 hover:text-brand-700 underline text-sm font-medium` | Text links |

### 2.9 Tables

| Element | Classes |
|---------|---------|
| Table wrapper | `overflow-x-auto rounded-lg border border-gray-200` |
| Table | `min-w-full divide-y divide-gray-200` |
| Header cell | `px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50` |
| Body cell | `px-4 py-3 text-sm text-gray-700 whitespace-nowrap` |
| Row hover | `hover:bg-gray-50 transition-colors` |
| Row border | `divide-y divide-gray-100` |

**Mobile table strategy:** At `sm:` breakpoint, table scrolls horizontally. Below that, transform to stacked card layout using `@media` or Tailwind responsive classes. Each row becomes a card with key-value pairs.

### 2.10 Form States

| State | Classes |
|-------|---------|
| Default | `border-gray-300 focus:border-brand-500 focus:ring-brand-500` |
| Error | `border-red-300 focus:border-red-500 focus:ring-red-500` |
| Success | `border-emerald-300 focus:border-emerald-500 focus:ring-emerald-500` |
| Disabled | `bg-gray-50 text-gray-500 cursor-not-allowed` |

## 3. Application Shell

### 3.1 Layout Structure

```
┌──────────────────────────────────────────────┐
│ Top Bar (logo, nav links desktop, user menu) │
├──────────┬───────────────────────────────────┤
│ Sidebar  │ Content Area                      │
│ (mobile  │ ┌───────────────────────────────┐ │
│  hidden) │ │ Flash Messages                │ │
│          │ ├───────────────────────────────┤ │
│          │ │ Page Header / Breadcrumb      │ │
│          │ ├───────────────────────────────┤ │
│          │ │ Page Content                  │ │
│          │ │                               │ │
│          │ │                               │ │
│          │ └───────────────────────────────┘ │
└──────────┴───────────────────────────────────┘
```

### 3.2 Top Bar

- Fixed width: `max-w-7xl mx-auto`.
- Left: Logo/brand name linking to dashboard.
- Center: Navigation links (desktop): Overview, Offers, Campaigns.
- Right: User dropdown (name, profile link, logout).
- Height: `h-16`.
- Background: `bg-white border-b border-gray-200`.

### 3.3 Sidebar (Mobile)

- Hidden by default on `sm:` and above.
- Triggered by hamburger button (Alpine.js `x-data`).
- Full height overlay with slide-in from left.
- Contains: nav links, user info, logout.
- Backdrop: semi-transparent black.
- Alpine toggle: `x-data="{ open: false }"`.

### 3.4 Logo/Brand

- Text-based: "CPAFlow" in brand-600 color, `font-bold text-xl`.
- Links to `/dashboard`.
- Application logo SVG available via `<x-application-logo>`.

### 3.5 User Menu

- Desktop: dropdown triggered by user name button.
- Shows: user name, email.
- Links: Profile, Logout.
- Mobile: inline in mobile nav panel.
- Alpine dropdown toggle.

### 3.6 Flash Messages

- Rendered at top of content area.
- Types: `success`, `error`, `warning`, `info`.
- Dismissable via Alpine.js (click X).
- Auto-dismiss after 5 seconds (Alpine `x-init="setTimeout(() => show = false, 5000)"`).
- Styles: colored left border, icon, message text.

### 3.7 Page Title

- Rendered in `<x-slot name="header">` pattern (already used by Breeze).
- `text-2xl font-semibold text-gray-900`.
- Optional breadcrumb: `nav > ol > li` with separators.

### 3.8 Active Navigation State

- Current route detection via `request()->routeIs('offers.*')`.
- Active link: `border-b-2 border-brand-400 text-gray-900`.
- Inactive link: `border-b-2 border-transparent text-gray-500 hover:text-gray-700`.

## 4. Exact Initial Navigation

| Label | Route | Route Name | Icon |
|-------|-------|------------|------|
| Overview | `/dashboard` | `dashboard` | Home icon |
| Offers | `/offers` | `offers.index` | Tag icon |
| Campaigns | `/campaigns` | `campaigns.index` | Megaphone icon |

Profile is accessed via the user dropdown, not the main nav.

No disabled/future entries.

## 5. Authentication Screens

### 5.1 Login

- Route: `GET /login` → `login.blade.php` (existing).
- Uses `<x-guest-layout>`.
- Fields: email, password, remember me.
- Links: "Forgot your password?" → `password.request`, "Register" → `register`.
- Validation errors via `<x-input-error>`.
- Session status via `<x-auth-session-status>`.
- Improvements: add CPAFlow branding, slightly refined spacing, consistent button styles.

### 5.2 Register

- Route: `GET /register` → `register.blade.php` (existing).
- Uses `<x-guest-layout>`.
- Fields: name, email, password, password_confirmation.
- Links: "Already registered?" → `login`.
- Improvements: same visual polish as login.

### 5.3 Forgot Password

- Route: `GET /forgot-password` → `forgot-password.blade.php` (existing).
- Uses `<x-guest-layout>`.
- Field: email.
- Improvements: same visual polish.

### 5.4 Reset Password

- Route: `GET /reset-password/{token}` → `reset-password.blade.php` (existing).
- Uses `<x-guest-layout>`.
- Fields: email, password, password_confirmation.
- Improvements: same visual polish.

### 5.5 Verify Email

- Route: `GET /verify-email` → `verify-email.blade.php` (existing).
- Uses `<x-guest-layout>`.
- Shows verification notice with resend button.
- Improvements: same visual polish.

### 5.6 Confirm Password

- Route: `GET /confirm-password` → `confirm-password.blade.php` (existing).
- Uses `<x-guest-layout>`.
- Field: password.
- Improvements: same visual polish.

### 5.7 Logout

- POST form in user dropdown menu.
- CSRF protected.
- Redirects to `/`.

## 6. Profile Screen

- Route: `GET /profile` → `profile/edit.blade.php` (existing).
- Uses `<x-app-layout>`.
- Three card sections:
  1. **Profile Information** — name, email inputs, save button. Uses `ProfileUpdateRequest`.
  2. **Update Password** — current password, new password, confirmation. Uses existing Breeze password update.
  3. **Delete Account** — danger zone with password confirmation. Uses existing Breeze delete.
- Each section: `bg-white shadow rounded-lg p-6`.
- Validation errors inline.
- Success flash: "Profile updated."
- Uses existing `UpdateUserProfileAction`.

## 7. Offer Screens

### 7.1 Offer Index

- Route: `GET /offers` → `OfferController@index`.
- Page title: "Offers".
- Header right: "Create Offer" button → `/offers/create`.
- Filters bar: search input (name), status dropdown (all/draft/active/suspended/archived).
- Table columns: Name, Destination URL (truncated), Payout, Status (badge), Actions.
- Actions: Edit (link), Archive (POST form with confirm), Campaigns (link to filtered campaigns).
- Empty state: illustration + "No offers yet" + "Create your first offer" CTA.
- Pagination: Laravel default pagination links.
- Mobile: card layout with stacked fields.

### 7.2 Offer Create

- Route: `GET /offers/create` → `OfferController@create`.
- Page title: "Create Offer".
- Breadcrumb: Offers > Create.
- Form fields: name, destination_url, payout, status (dropdown), description (textarea).
- Submit: POST `/offers` → `OfferController@store`.
- Validation: reuse `StoreOfferRequest` rules (web-specific Form Request created).
- On success: redirect to `/offers` with flash "Offer created."
- On error: re-render form with errors.

### 7.3 Offer Edit

- Route: `GET /offers/{offer}/edit` → `OfferController@edit`.
- Page title: "Edit Offer".
- Breadcrumb: Offers > Edit > {offer name}.
- Same form as create, pre-filled.
- Submit: PATCH `/offers/{offer}` → `OfferController@update`.
- Authorization: `$user->can('update', $offer)` via `OfferPolicy`.
- On success: redirect to `/offers` with flash "Offer updated."
- Foreign offer: 403.

### 7.4 Offer Details

- **Decision: No dedicated details page.** The index shows key fields; edit provides full form. Adding a details page adds navigation without backend value for this demo phase.

## 8. Campaign Screens

### 8.1 Campaign Index

- Route: `GET /campaigns` → `CampaignController@index`.
- Page title: "Campaigns".
- Header right: "Create Campaign" button → `/campaigns/create`.
- Table columns: Name, Offer (name), Traffic Source, Budget, Status (badge), Actions.
- Actions: Edit (link), Activate (POST, only if draft/suspended), Suspend (POST, only if active).
- Empty state: "No campaigns yet" + "Create your first campaign" CTA.
- Mobile: card layout.

### 8.2 Campaign Create

- Route: `GET /campaigns/create` → `CampaignController@create`.
- Page title: "Create Campaign".
- Breadcrumb: Campaigns > Create.
- Form fields: offer_id (dropdown of user's non-archived offers), name, traffic_source, budget.
- Submit: POST `/campaigns` → `CampaignController@store`.
- Validation: web-specific Form Request based on `StoreCampaignRequest` rules.
- On success: redirect to `/campaigns` with flash "Campaign created."

### 8.3 Campaign Edit

- Route: `GET /campaigns/{campaign}/edit` → `CampaignController@edit`.
- Page title: "Edit Campaign".
- Same form as create, pre-filled.
- Submit: PATCH `/campaigns/{campaign}` → `CampaignController@update`.
- Authorization: `CampaignPolicy@update`.
- On success: redirect to `/campaigns` with flash "Campaign updated."

### 8.4 Campaign Lifecycle Actions

| Action | Method | Route | Condition | Confirmation |
|--------|--------|-------|-----------|--------------|
| Activate | POST | `/campaigns/{campaign}/activate` | Draft or Suspended | Yes ("Are you sure?") |
| Suspend | POST | `/campaigns/{campaign}/suspend` | Active | Yes ("Are you sure?") |

- Use POST forms with `@csrf @method('POST')`.
- Authorization: `CampaignPolicy@activate` / `CampaignPolicy@suspend`.
- On domain error (`InvalidCampaignTransition`): redirect back with error flash.
- Buttons conditionally rendered based on current status.

### 8.5 Campaign Detail / Tracking Links

- Route: `GET /campaigns/{campaign}` → `CampaignController@show`.
- Page title: "Campaign: {name}".
- Shows: campaign info card, offer reference, status badge, lifecycle action buttons.
- **Tracking Links section** at the bottom:
  - If active: "Generate Tracking Link" button.
  - If not active: disabled button with tooltip "Campaign must be active."
  - After generation: display the full tracking URL in a read-only input with "Copy" button.
  - List of existing tracking links (if multiple): code, created_at, full URL.
  - Copy button uses `navigator.clipboard.writeText()` via Alpine.js.
- URL format: `{APP_URL}/t/{code}` — constructed in Controller, not hardcoded in Blade.

## 9. Tracking-Link UX

### 9.1 Generation Flow

1. User navigates to Campaign detail page.
2. If campaign is active, "Generate Tracking Link" button is enabled.
3. User clicks button → POST form to `/campaigns/{campaign}/tracking-links`.
4. Backend validates: owner, active status, generates code.
5. Redirect back to campaign detail with flash "Tracking link generated."
6. The newly generated link appears in the tracking links list.

### 9.2 Display and Copy

- Each tracking link shows: code, created-at, full URL.
- Full URL in a read-only `<input>` or `<code>` block.
- "Copy" button next to URL.
- Copy uses: `navigator.clipboard.writeText(url)`.
- Visual feedback: "Copied!" text appears for 2 seconds (Alpine.js).

### 9.3 Multiple Links

- If user generates multiple links, they appear as a list.
- Each has its own URL and copy button.
- Ordered by `created_at DESC`.

## 10. Public Tracking-Link Demo Flow

Evaluator walkthrough:

1. Login → navigate to Offers → create an Offer with a real destination URL.
2. Navigate to Campaigns → create a Campaign linked to that Offer.
3. Activate the Campaign.
4. On Campaign detail, click "Generate Tracking Link."
5. Copy the generated URL.
6. Open the URL in a new tab.
7. Observe: 302 redirect to the Offer destination URL.
8. (Backend records the click.)

No click analytics or records are shown in the UI for this phase.

## 11. Exact Planned Web Routes

### 11.1 New Routes

| Method | URI | Controller@method | Route Name |
|--------|-----|-------------------|------------|
| GET | `/offers` | `OfferController@index` | `offers.index` |
| GET | `/offers/create` | `OfferController@create` | `offers.create` |
| POST | `/offers` | `OfferController@store` | `offers.store` |
| GET | `/offers/{offer}/edit` | `OfferController@edit` | `offers.edit` |
| PATCH | `/offers/{offer}` | `OfferController@update` | `offers.update` |
| POST | `/offers/{offer}/archive` | `OfferController@archive` | `offers.archive` |
| GET | `/campaigns` | `CampaignController@index` | `campaigns.index` |
| GET | `/campaigns/create` | `CampaignController@create` | `campaigns.create` |
| POST | `/campaigns` | `CampaignController@store` | `campaigns.store` |
| GET | `/campaigns/{campaign}` | `CampaignController@show` | `campaigns.show` |
| GET | `/campaigns/{campaign}/edit` | `CampaignController@edit` | `campaigns.edit` |
| PATCH | `/campaigns/{campaign}` | `CampaignController@update` | `campaigns.update` |
| POST | `/campaigns/{campaign}/activate` | `CampaignController@activate` | `campaigns.activate` |
| POST | `/campaigns/{campaign}/suspend` | `CampaignController@suspend` | `campaigns.suspend` |
| POST | `/campaigns/{campaign}/tracking-links` | `CampaignController@storeTrackingLink` | `campaigns.tracking-links.store` |

### 11.2 Existing Routes (Unchanged)

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/` | `welcome` (not a named route) |
| GET | `/dashboard` | `dashboard` |
| GET | `/profile` | `profile.edit` |
| PATCH | `/profile` | `profile.update` |
| DELETE | `/profile` | `profile.destroy` |
| GET | `/t/{code}` | `tracking.redirect` |
| GET/POST | `/login` | `login` |
| GET/POST | `/register` | `register` |
| GET/POST | `/forgot-password` | `password.request` / `password.email` |
| GET/POST | `/reset-password/{token}` | `password.reset` / `password.store` |
| GET | `/verify-email` | `verification.notice` |
| POST | `/email/verification-notification` | `verification.send` |
| GET | `/confirm-password` | `password.confirm` |
| PUT | `/password` | `password.update` |
| POST | `/logout` | `logout` |

### 11.3 Route Grouping

All new routes are inside the existing `auth` middleware group in `routes/web.php`.

```php
Route::middleware('auth')->group(function () {
    // ... existing profile routes ...

    // Offers
    Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('/offers/create', [OfferController::class, 'create'])->name('offers.create');
    Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
    Route::get('/offers/{offer}/edit', [OfferController::class, 'edit'])->name('offers.edit');
    Route::patch('/offers/{offer}', [OfferController::class, 'update'])->name('offers.update');
    Route::post('/offers/{offer}/archive', [OfferController::class, 'archive'])->name('offers.archive');

    // Campaigns
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::post('/campaigns/{campaign}/activate', [CampaignController::class, 'activate'])->name('campaigns.activate');
    Route::post('/campaigns/{campaign}/suspend', [CampaignController::class, 'suspend'])->name('campaigns.suspend');
    Route::post('/campaigns/{campaign}/tracking-links', [CampaignController::class, 'storeTrackingLink'])->name('campaigns.tracking-links.store');
});
```

## 12. Planned Web Controllers

### 12.1 OfferController

**Namespace:** `App\Http\Controllers`

**File:** `app/Http/Controllers/OfferController.php`

| Method | Responsibility | Reuses |
|--------|---------------|--------|
| `index()` | Query user's offers, apply search/status filters, paginate, return view | `Offer` scopes |
| `create()` | Return create form view | — |
| `store(StoreOfferWebRequest $request)` | Delegate to `CreateOfferAction`, redirect with flash | `CreateOfferAction` |
| `edit(Offer $offer)` | Authorize via `OfferPolicy@update`, return edit form view | `OfferPolicy` |
| `update(UpdateOfferWebRequest $request, Offer $offer)` | Authorize, delegate to `UpdateOfferAction`, redirect with flash | `OfferPolicy`, `UpdateOfferAction` |
| `archive(Offer $offer)` | Authorize via `OfferPolicy@archive`, delegate to `ArchiveOfferAction`, redirect with flash | `OfferPolicy`, `ArchiveOfferAction` |

### 12.2 CampaignController

**File:** `app/Http/Controllers/CampaignController.php`

| Method | Responsibility | Reuses |
|--------|---------------|--------|
| `index()` | Query user's campaigns via Offer ownership, eager-load offer, paginate, return view | `Campaign` query |
| `create()` | Return create form with user's non-archived offers for dropdown | `Offer` query |
| `store(StoreCampaignWebRequest $request)` | Validate, resolve Offer, authorize via `OfferPolicy@createCampaign`, delegate to `CreateCampaignAction`, redirect | `CreateCampaignAction`, `OfferPolicy` |
| `show(Campaign $campaign)` | Authorize via `CampaignPolicy@view`, eager-load trackingLinks, return view | `CampaignPolicy` |
| `edit(Campaign $campaign)` | Authorize via `CampaignPolicy@update`, return edit form with offers | `CampaignPolicy` |
| `update(UpdateCampaignWebRequest $request, Campaign $campaign)` | Authorize, delegate to `UpdateCampaignAction`, redirect | `CampaignPolicy`, `UpdateCampaignAction` |
| `activate(Campaign $campaign)` | Authorize via `CampaignPolicy@activate`, delegate to `ActivateCampaignAction`, redirect | `CampaignPolicy`, `ActivateCampaignAction` |
| `suspend(Campaign $campaign)` | Authorize via `CampaignPolicy@suspend`, delegate to `SuspendCampaignAction`, redirect | `CampaignPolicy`, `SuspendCampaignAction` |
| `storeTrackingLink(Campaign $campaign)` | Authorize via `CampaignPolicy@generateTrackingLink`, delegate to `GenerateTrackingLinkAction`, redirect | `CampaignPolicy`, `GenerateTrackingLinkAction` |

### 12.3 Business Logic Reuse Strategy

```
Blade Controller
    ↓ receives HTTP request
    ↓ validates via Form Request (web-specific)
    ↓ authorizes via Policy (same Policy as API)
    ↓ delegates to Action (same Action as API)
    ↓ returns redirect with flash
```

- **No duplication** of Offer/Campaign/TrackingLink business rules.
- **No direct HTTP calls** to the API from Blade Controllers.
- **Same Actions** shared between web and API controllers.
- **Same Policies** shared between web and API controllers.
- **Web-specific Form Requests** handle web-specific validation (e.g., accepting `_method` override, different input format).

## 13. Policy / Authorization Strategy

| Resource | Policy | Web Controller Usage |
|----------|--------|---------------------|
| Offer | `OfferPolicy@update` | `OfferController@edit`, `OfferController@update` |
| Offer | `OfferPolicy@archive` | `OfferController@archive` |
| Campaign | `CampaignPolicy@view` | `CampaignController@show` |
| Campaign | `CampaignPolicy@update` | `CampaignController@edit`, `CampaignController@update` |
| Campaign | `CampaignPolicy@activate` | `CampaignController@activate` |
| Campaign | `CampaignPolicy@suspend` | `CampaignController@suspend` |
| Campaign | `CampaignPolicy@generateTrackingLink` | `CampaignController@storeTrackingLink` |
| Offer | `OfferPolicy@createCampaign` | `CampaignController@store` |

Authorization is enforced **server-side** in every Controller method. UI elements (buttons, links) are also conditionally rendered based on `$user->can()` for UX, but server-side enforcement is the source of truth.

## 14. Form Validation Strategy

### 14.1 Web-Specific Form Requests

New Form Requests under `app/Http/Requests/`:

| Request | Purpose | Rules Source |
|---------|---------|-------------|
| `StoreOfferWebRequest` | Offer creation from web | Mirrors `StoreOfferRequest` rules + `authorize()` using auth |
| `UpdateOfferWebRequest` | Offer update from web | Mirrors `UpdateOfferRequest` rules + `authorize()` using auth |
| `StoreCampaignWebRequest` | Campaign creation from web | Mirrors `StoreCampaignRequest` rules + Offer resolution |
| `UpdateCampaignWebRequest` | Campaign update from web | Mirrors `UpdateCampaignRequest` rules |

### 14.2 Validation Display

- Each form field shows `<x-input-error :messages="$errors->get('field')" />` below the input.
- Form inputs retain `old()` values on validation failure.
- Error summary at top of form (optional, via `$errors->any()`).

### 14.3 Flash Messages

| Event | Flash Key | Message |
|-------|-----------|---------|
| Offer created | `success` | "Offer created successfully." |
| Offer updated | `success` | "Offer updated successfully." |
| Offer archived | `success` | "Offer archived successfully." |
| Campaign created | `success` | "Campaign created successfully." |
| Campaign updated | `success` | "Campaign updated successfully." |
| Campaign activated | `success` | "Campaign activated successfully." |
| Campaign suspended | `success` | "Campaign suspended successfully." |
| Tracking link generated | `success` | "Tracking link generated successfully." |
| Profile updated | `status` | "profile-updated" (existing Breeze convention) |
| Domain error | `error` | Exception message (e.g., "This campaign cannot be activated.") |
| Authorization failure | 403/404 | Handled by Laravel exception rendering |

## 15. Flash / Error-State Strategy

### 15.1 Flash Message Component

New component: `components/flash-message.blade.php`.

```blade
@if (session('success'))
    <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <icon /> <p class="text-sm text-emerald-700">{{ session('success') }}</p>
            </div>
            <button @click="show = false">x</button>
        </div>
    </div>
@endif

@if (session('error'))
    <div class="rounded-lg bg-red-50 border border-red-200 p-4" x-data="{ show: true }" x-show="show">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <icon /> <p class="text-sm text-red-700">{{ session('error') }}</p>
            </div>
            <button @click="show = false">x</button>
        </div>
    </div>
@endif
```

### 15.2 Error Pages

- 403: "Unauthorized" — simple card with back link.
- 404: "Not Found" — simple card with back link.
- 419: "Page Expired" — CSRF token mismatch, link to refresh.
- 500: "Server Error" — generic message.

Use Laravel's existing exception rendering (no custom error views unless needed).

## 16. Responsive Strategy

### 16.1 Breakpoints

| Breakpoint | Width | Behavior |
|------------|-------|----------|
| Default | < 640px | Mobile: stacked layout, hamburger nav, card-based tables |
| `sm:` | ≥ 640px | Tablet: horizontal nav appears, basic table layout |
| `md:` | ≥ 768px | Desktop: full sidebar/tables, multi-column forms |
| `lg:` | ≥ 1024px | Wide desktop: max-width containers |

### 16.2 Navigation

- Mobile: hamburger → slide-in panel (Alpine.js).
- `sm:` and above: horizontal top bar navigation.
- No sidebar — top bar navigation is sufficient for 4 items.

### 16.3 Tables

- Desktop: full horizontal table.
- Mobile: each row becomes a card with:
  - Primary field (name) as title.
  - Secondary fields as key-value pairs.
  - Actions as button row at bottom.
- Triggered via Tailwind responsive classes: `hidden sm:table-cell`, `sm:hidden`.

### 16.4 Forms

- Single column on all screen sizes.
- Max-width: `max-w-2xl` for forms, `max-w-7xl` for table pages.
- Button row: horizontal on desktop, stacked on mobile.

## 17. Accessibility Strategy

| Requirement | Implementation |
|-------------|---------------|
| Semantic headings | `<h1>` for page title, `<h2>` for sections, `<h3>` for cards |
| Form labels | `<x-input-label>` with `for` attribute matching input `id` |
| Keyboard navigation | All interactive elements are focusable; visible focus rings |
| Focus states | `focus:ring-2 focus:ring-brand-500 focus:ring-offset-2` |
| Button labels | `aria-label` on icon-only buttons (copy, dismiss, hamburger) |
| Status badges | Not color-only: include text label + optional dot icon |
| Contrast | Tailwind default colors meet WCAG AA (4.5:1 for text) |
| Skip link | Optional: "Skip to main content" link at top of page |
| ARIA attributes | `aria-current="page"` on active nav link; `role="alert"` on flash messages |
| Alt text | Decorative icons use `aria-hidden="true"`; meaningful icons have labels |

## 18. JavaScript Strategy

### 18.1 Stack

- **Alpine.js** (already installed) — reactive behavior without SPA overhead.
- **No new frameworks** — no React, Vue, or Livewire.
- **No build tool changes** — Vite + Alpine already configured.

### 18.2 JavaScript Features

| Feature | Implementation |
|---------|---------------|
| Mobile navigation toggle | Alpine.js `x-data="{ open: false }"` on nav |
| Dropdown menus | Alpine.js `x-data="{ open: false }"` on user menu |
| Copy to clipboard | Alpine.js + `navigator.clipboard.writeText()` |
| Dismissible flash | Alpine.js `x-data="{ show: true }"` + `x-show` + `setTimeout` |
| Confirmation dialogs | `window.confirm()` for destructive actions (archive, suspend) |
| Form submission | Standard HTML forms with `@csrf` — no JS form handling |

### 18.3 Bundle Size

- Alpine.js: ~15KB gzipped (already in bundle).
- Additional JS: <2KB (copy logic, flash dismiss).
- Total JS footprint: minimal, no runtime overhead.

## 19. Reusable Blade Components Planned

### 19.1 New Components

| Component | File | Purpose |
|-----------|------|---------|
| `<x-page-header>` | `components/page-header.blade.php` | Page title + optional action button |
| `<x-empty-state>` | `components/empty-state.blade.php` | Empty list placeholder |
| `<x-status-badge>` | `components/status-badge.blade.php` | Status indicator with color |
| `<x-flash-message>` | `components/flash-message.blade.php` | Flash notification |
| `<x-search-input>` | `components/search-input.blade.php` | Search/filter input |
| `<x-confirm-button>` | `components/confirm-button.blade.php` | POST button with JS confirm |
| `<x-table>` | `components/table.blade.php` | Responsive table wrapper |
| `<x-form-group>` | `components/form-group.blade.php` | Label + input + error group |
| `<x-tracking-url>` | `components/tracking-url.blade.php` | Read-only URL with copy button |

### 19.2 Existing Components (Enhanced or Reused)

| Component | Status |
|-----------|--------|
| `<x-app-layout>` | Reused as-is |
| `<x-guest-layout>` | Reused as-is |
| `<x-nav-link>` | Enhanced for new nav items |
| `<x-responsive-nav-link>` | Enhanced for mobile nav |
| `<x-primary-button>` | Reused as-is |
| `<x-secondary-button>` | Reused as-is |
| `<x-danger-button>` | Reused as-is |
| `<x-text-input>` | Reused as-is |
| `<x-input-label>` | Reused as-is |
| `<x-input-error>` | Reused as-is |
| `<x-dropdown>` | Reused for user menu |
| `<x-dropdown-link>` | Reused for user menu |
| `<x-application-logo>` | Reused or replaced with CPAFlow branding |
| `<x-auth-session-status>` | Reused for auth screens |

## 20. Complete Automated Test Scenarios

All tests are Pest feature tests under `tests/Feature/Web/`.

### 20.1 Guest Access (4 tests)

1. Guest redirect: `GET /offers` → 302 to `/login`.
2. Guest redirect: `GET /campaigns` → 302 to `/login`.
3. Guest redirect: `GET /offers/create` → 302 to `/login`.
4. Guest redirect: `GET /campaigns/create` → 302 to `/login`.

### 20.2 Application Shell (2 tests)

5. Authenticated user sees application shell: `GET /dashboard` → 200, contains navigation links.
6. Navigation links are present: offers, campaigns, profile in response HTML.

### 20.3 Offer Index (3 tests)

7. User sees only their own offers: create 2 users, each with offers, assert only own offers visible.
8. Foreign offer not visible: user A's offers not in user B's index.
9. Offers empty state renders: user with no offers sees empty state message.

### 20.4 Offer Create (3 tests)

10. Offer create form renders: `GET /offers/create` → 200, contains form fields.
11. Offer creation succeeds: POST valid data → 302, database has offer.
12. Offer validation errors render: POST invalid data → 302 back with errors.

### 20.5 Offer Edit (3 tests)

13. Offer edit form renders: `GET /offers/{offer}/edit` → 200, pre-filled.
14. Offer update succeeds: PATCH valid data → 302, database updated.
15. Foreign offer cannot be edited: `GET /offers/{other}/edit` → 403.

### 20.6 Offer Archive (2 tests)

16. Offer archive succeeds: POST → 302, status becomes archived.
17. Foreign offer cannot be archived: POST → 403.

### 20.7 Campaign Index (3 tests)

18. User sees only their own campaigns: ownership isolation.
19. Foreign campaign not visible: other user's campaigns excluded.
20. Campaigns empty state renders: no campaigns message.

### 20.8 Campaign Create (3 tests)

21. Campaign create form renders with eligible offers: dropdown populated.
22. Campaign creation succeeds: POST valid data → 302.
23. Archived offer not in dropdown: only non-archived offers shown.

### 20.9 Campaign Edit (3 tests)

24. Campaign edit form renders: pre-filled.
25. Campaign update succeeds: PATCH → 302.
26. Foreign campaign cannot be edited: 403.

### 20.10 Campaign Lifecycle (4 tests)

27. Campaign activate succeeds: draft → active, 302.
28. Campaign suspend succeeds: active → suspended, 302.
29. Foreign campaign cannot be activated: 403.
30. Domain transition error displayed: active → active shows error flash.

### 20.11 Tracking Links (3 tests)

31. Active campaign can generate tracking link: POST → 302, link created.
32. Draft campaign cannot generate tracking link: POST → 403 or error.
33. Generated tracking URL is displayed in response HTML.

### 20.12 Profile (3 tests)

34. Profile screen renders: `GET /profile` → 200.
35. Profile validation works: invalid email → errors.
36. Profile update succeeds: valid data → 302.

### 20.13 Navigation & UX (3 tests)

37. Flash messages render after successful mutation.
38. Active navigation state is correct: current route highlighted.
39. CSRF protection enabled: form without token returns 403.

### 20.14 Regression (2 tests)

40. Existing API tests remain unaffected: `php artisan test tests/Feature/Api/`.
41. Existing auth tests remain unaffected: `php artisan test tests/Feature/Auth/`.

**Total: 41 test scenarios (see design.md for full breakdown).**

## 21. Manual Browser Smoke-Test Plan

1. Login with credentials.
2. Navigate to Dashboard — verify shell and navigation.
3. Navigate to Offers — verify empty state.
4. Click "Create Offer" — fill form — submit.
5. Verify success flash and offer in list.
6. Edit the offer — change name — submit.
7. Verify update flash and updated name.
8. Archive the offer — confirm — verify archived badge.
9. Navigate to Campaigns — verify empty state.
10. Click "Create Campaign" — select offer — fill form — submit.
11. Verify success flash and campaign in list.
12. Click "Activate" on campaign — confirm — verify active badge.
13. Click into campaign detail — verify tracking link section.
14. Click "Generate Tracking Link" — verify link appears.
15. Click "Copy" — verify clipboard contains URL.
16. Open tracking URL in new tab — verify redirect to offer destination.
17. Click "Suspend" on campaign — confirm — verify suspended badge.
18. Resize to mobile — verify hamburger navigation works.
19. Navigate via mobile menu — verify all pages accessible.
20. Click user dropdown — go to Profile — edit name — save.
21. Verify profile update flash.
22. Click Logout — verify redirected to login page.

## 22. Scope Exclusions

| Feature | Reason |
|---------|--------|
| Conversions UI | Backend not implemented (KAN-16+) |
| Expenses UI | Backend not implemented |
| Dashboard analytics | Backend not implemented |
| Period filters | No analytics backend |
| Click analytics | No click analytics backend |
| Unique visitors | No unique visitor tracking UI |
| AI analysis | Backend not implemented |
| Generated captions/hooks | Backend not implemented |
| Admin UI | Admin role exists but no admin features |
| Docker | Infrastructure story |
| Azure deployment | Infrastructure story |
| Frontend for KAN-16+ | Backend not implemented |
