# CPAFlow AI — Premium UI/UX Redesign Plan (Revised)

**Date:** 2026-08-05
**Revision:** Phase 1+2 implemented. Backend unchanged. No dependency changes.
**Scope:** Frontend visual/UX overhaul — Blade templates, Tailwind config, CSS, Alpine.js
**Current state:** Functional Breeze scaffold + basic Blade pages
**Target state:** Polished, premium SaaS-quality admin panel
**Branch:** `feature/ui-ux-premium-polish`
**Backend freeze:** ACTIVE — no changes to policies, actions, models, schema, migrations, API contracts, business logic

---

## 1. Audit Summary

### 1.1 Current Frontend Stack
| Technology | Version | Notes |
|---|---|---|
| Laravel | 13.x | Blade templating |
| Tailwind CSS | ^3.1.0 | With `@tailwindcss/forms` ^0.5.2 |
| Alpine.js | ^3.4.2 | No extra plugins |
| Vite | ^8.0.0 | `laravel-vite-plugin` ^3.1 |
| Font | Figtree | Loaded via Google Fonts |
| Icons | Inline SVG | No icon library. No new dependencies planned. |

### 1.2 Tailwind Config (`tailwind.config.js`)
- Brand color: `brand-50..900` mapped to indigo-500 (`#6366f1`) scale
- Custom shadows: `shadow-card`, `shadow-card-hover`
- Font: `Figtree` sans-serif
- Forms plugin enabled

### 1.3 Verified File Inventory

**Layouts (3):**
- `layouts/app.blade.php` — main authenticated layout (bg-gray-50, max-w-7xl, nav + flash)
- `layouts/guest.blade.php` — auth layout (centered card, shadow-card)
- `layouts/navigation.blade.php` — top nav bar (logo, nav links, user dropdown)

**Pages (9):**
- `dashboard.blade.php` — greeting, quick actions, inventory overview, period filter, activity metrics (clicks, conversions, revenue, expenses, profit), recent offers/campaigns tables, empty state
- `offers/index.blade.php` — search + status filter table, empty state
- `offers/create.blade.php` — wrapper with form partial
- `offers/edit.blade.php` — wrapper with form partial
- `campaigns/index.blade.php` — empty state or table with status/actions
- `campaigns/show.blade.php` — tracking links, campaign details, landing page, status actions (activate/suspend)
- `campaigns/create.blade.php` — wrapper with form partial
- `campaigns/edit.blade.php` — wrapper with form partial
- `profile/edit.blade.php` — 3-card layout (info, password, delete)

**Partials (4):**
- `offers/partials/form.blade.php` — name, destination_url, payout, status, description
- `campaigns/partials/form.blade.php` — offer_id, name, traffic_source, budget
- `profile/partials/update-profile-information-form.blade.php` — name, email
- `profile/partials/delete-user-form.blade.php` — danger zone with modal

**Auth (6):**
- `auth/login.blade.php`
- `auth/register.blade.php`
- `auth/forgot-password.blade.php`
- `auth/reset-password.blade.php`
- `auth/verify-email.blade.php`
- `auth/confirm-password.blade.php`

**Components (17):**
- Layout: `page-header`, `nav-link`
- Form: `text-input`, `input-label`, `input-error`, `form-group`, `search-input`
- Button: `primary-button` (bg-gray-800), `secondary-button` (bg-white), `danger-button` (bg-red-600), `confirm-button`
- Display: `status-badge`, `empty-state`, `flash-message`, `tracking-url`
- Overlay: `modal`, `dropdown`

---

## 2. Verified Backend Data (What Actually Exists)

### 2.1 Dashboard (`DashboardController::index`)
The Blade view receives `$statistics` with these keys:
- `offer_count` — all-time count
- `campaign_count` — all-time count
- `active_campaign_count` — all-time count
- `click_count` — period-filtered
- `conversion_count` — period-filtered
- `revenue` — period-filtered, approved only
- `total_expenses` — period-filtered
- `profit` — period-filtered

Also receives: `$hasEligibleOffers`, `$recentOffers`, `$recentCampaigns`, `$activePeriod`, `$activeFrom`, `$activeTo`.

**No trend data, no growth percentages, no pending review counts are provided by the backend.** The redesign must use only these values.

### 2.2 Campaign Form (Real Fields Only)
The campaign form (`campaigns/partials/form.blade.php`) supports exactly these fields:
- `offer_id` — select dropdown (hidden on edit, display-only)
- `name` — text input
- `traffic_source` — text input
- `budget` — number input (decimal, min 0)

**No other fields exist.** There is no description, no spend, no impressions, no clicks, no conversions, no revenue, no status field on the form. Status is managed via activate/suspend actions only.

### 2.3 Campaign Show (Real Content)
The campaign show page displays:
- Tracking links (generated via `storeTrackingLink` action, displayed with `tracking-url` component)
- Campaign details (status, linked offer, traffic source, budget, created date)
- Landing page URL (from linked offer's `destination_url`)
- Status action buttons: Activate (draft/suspended → active), Suspend (active → suspended)

**No expenses section, no conversions section, no AI analysis section exist in the current Blade views.** These features exist only as API endpoints. They will be designed as new tabs in the campaign show page, consuming existing API data.

### 2.4 Existing API Endpoints (Available for Consumption)
The API (`routes/api.php`) provides these endpoints that the campaign show tabs will consume:
- `GET /api/v1/campaigns/{campaign}/expenses` — list expenses
- `POST /api/v1/campaigns/{campaign}/expenses` — create expense
- `PATCH /api/v1/campaigns/{campaign}/expenses/{expense}` — update expense
- `DELETE /api/v1/campaigns/{campaign}/expenses/{expense}` — delete expense
- `POST /api/v1/campaigns/{campaign}/conversions` — create conversion
- `POST /api/v1/campaigns/{campaign}/conversions/{conversion}/approve` — approve (no body)
- `POST /api/v1/campaigns/{campaign}/conversions/{conversion}/reject` — reject (no body)
- `GET /api/v1/offers/{offer}/analysis` — AI analysis result
- `POST /api/v1/offers/{offer}/analyze` — trigger AI analysis
- `GET /api/v1/offers/{offer}/generations` — AI generation history
- `POST /api/v1/offers/{offer}/generate` — trigger AI generation

All API endpoints use `auth:sanctum` middleware. The Blade/Alpine integration must use same-origin credentials with CSRF token (Sanctum SPA authentication).

### 2.5 Offer Form (Real Fields Only)
- `name` — text input
- `destination_url` — URL input
- `payout` — number input (decimal, min 0)
- `status` — select (draft/active/suspended; archived excluded from form)
- `description` — textarea (optional)

---

## 3. UX & Visual Issues Identified

### 3.1 Global / Layout
| # | Issue | Severity |
|---|---|---|
| G-1 | No page transition animations | Medium |
| G-2 | Flash messages are top-right corner only, easy to miss | Medium |
| G-3 | No loading states on async actions | Medium |
| G-4 | `max-w-7xl` wastes space on large screens | Medium |

### 3.2 Dashboard
| # | Issue | Severity |
|---|---|---|
| D-1 | "Welcome back, Admin!" header is generic, no user name | Medium |
| D-2 | Period filter is basic `<select>` dropdown | Low |
| D-3 | Activity section uses raw `<ul>` list, no visual hierarchy | Medium |
| D-4 | Empty state is functional but bland | Low |

### 3.3 Offers
| # | Issue | Severity |
|---|---|---|
| O-1 | Table has no row hover effect | Medium |
| O-2 | No visual payout amount styling (plain text) | Medium |
| O-3 | Empty state search icon is generic | Low |

### 3.4 Campaigns
| # | Issue | Severity |
|---|---|---|
| C-1 | Campaign show page is flat — all info on one scroll, no organization | High |
| C-2 | Status action buttons have no visual distinction | High |
| C-3 | Tracking URL copy feedback is minimal | Medium |
| C-4 | No expenses/conversions/AI sections (only exist as API) | High |

### 3.5 Profile
| # | Issue | Severity |
|---|---|---|
| P-1 | Three separate cards feel disconnected | Low |
| P-2 | Delete account danger zone has no visual warning treatment | Medium |

### 3.6 Auth
| # | Issue | Severity |
|---|---|---|
| A-1 | Auth pages are plain Breeze default | Medium |
| A-2 | No brand logo on auth pages | Low |
| A-3 | Guest layout has no background treatment | Low |

### 3.7 Components
| # | Issue | Severity |
|---|---|---|
| K-1 | `primary-button` uses `bg-gray-800` instead of brand color | High |
| K-2 | `status-badge` only has 4 states, missing pending/approved/rejected | High |
| K-3 | `empty-state` icons are small (h-8 w-8) and low contrast | Medium |
| K-4 | `confirm-button` uses native `confirm()` dialog | Medium |

---

## 4. Design System

### 4.1 Color Palette

```css
/* Primary — Indigo (existing brand, keep) */
brand-50:  #eef2ff
brand-100: #e0e7ff
brand-200: #c7d2fe
brand-300: #a5b4fc
brand-400: #818cf8
brand-500: #6366f1  ← Primary
brand-600: #4f46e5
brand-700: #4338ca
brand-800: #3730a3
brand-900: #312e81

/* Neutral — Cool Gray */
gray-50:  #f9fafb
gray-100: #f3f4f6
gray-200: #e5e7eb
gray-300: #d1d5db
gray-400: #9ca3af
gray-500: #6b7280
gray-600: #4b5563
gray-700: #374151
gray-800: #1f2937
gray-900: #111827

/* Semantic */
success-50:  #f0fdf4
success-500: #22c55e
success-600: #16a34a
warning-50:  #fffbeb
warning-500: #f59e0b
warning-600: #d97706
danger-50:  #fef2f2
danger-500: #ef4444
danger-600: #dc2626
info-50:  #eff6ff
info-500: #3b82f6
info-600: #2563eb

/* Conversion statuses */
pending-50:  #fffbeb
pending-500: #f59e0b
approved-50:  #f0fdf4
approved-500: #22c55e
rejected-50:  #fef2f2
rejected-500: #ef4444
```

### 4.2 Typography

| Element | Class | Notes |
|---|---|---|
| Page title | `text-2xl font-bold text-gray-900` | |
| Section title | `text-lg font-semibold text-gray-900` | |
| Card title | `text-sm font-semibold text-gray-900` | |
| Body | `text-sm text-gray-600` | |
| Caption/muted | `text-xs text-gray-500` | |
| Metric number | `text-3xl font-bold text-gray-900` | Dashboard stats |
| Badge label | `text-xs font-medium` | Status badges |
| Button primary | `text-sm font-medium` | Normal readable labels, NOT forced uppercase |
| Nav link | `text-sm font-medium` | Top nav |

### 4.3 Spacing & Layout

```css
page-padding:      py-8 px-4 sm:px-6 lg:px-8
content-max-width: max-w-7xl mx-auto
card-padding:      p-6 (standard), p-4 (compact)
card-gap:          gap-6 (between cards)
section-gap:       space-y-8 (between page sections)
```

### 4.4 Shadows

```css
shadow-card:        0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)
shadow-card-hover:  0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)
shadow-elevated:    0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)
```

### 4.5 Border Radius

```css
rounded-card:   rounded-lg  (14-16px for major cards)
rounded-button: rounded-md  (buttons, inputs)
rounded-badge:  rounded-full (status badges)
```

### 4.6 Transitions

```css
transition-base:   transition duration-150 ease-in-out  (120-220ms target)
transition-slow:   transition duration-220 ease-in-out
transition-fast:   transition duration-100 ease-in-out
```

---

## 5. Component System

### 5.1 Button System (4 variants)

| Variant | Current | Revised |
|---|---|---|
| **Primary** | `bg-gray-800` | `bg-brand-600 hover:bg-brand-700 active:bg-brand-800` |
| **Secondary** | `bg-white border-gray-300` | `bg-white border-gray-300 hover:bg-gray-50` (keep) |
| **Ghost** | (does not exist) | `bg-transparent hover:bg-gray-100 text-gray-700` |
| **Danger** | `bg-red-600` | `bg-red-600 hover:bg-red-500 active:bg-red-700` (keep) |

**Label style:** Normal readable text (`text-sm font-medium`). No forced uppercase/tracking-widest. Examples: "Create offer", "Save changes", "Activate".

### 5.2 `status-badge` — 7 States

| Status | Color | Dot |
|---|---|---|
| draft | gray | gray-400 |
| active | green | green-500 |
| suspended | amber | amber-500 |
| archived | gray | gray-300 |
| pending | amber | amber-500 (pulse animation) |
| approved | green | green-500 |
| rejected | red | red-500 |

### 5.3 `empty-state` — Upgraded
- Icon: h-12 w-12 inside a light gray circle (`bg-gray-100 rounded-full`)
- Larger title, more spacing
- Optional description and action button

### 5.4 `confirm-button` — Alpine Modal
- Replace native `confirm()` with Alpine-driven styled confirmation modal
- Cancel / Confirm buttons with proper styling

### 5.5 `search-input` — Clear Button
- Add X button when value is present (Alpine.js `x-model` + `@click`)

### 5.6 `tracking-url` — Copy Feedback
- Tooltip "Copied!" + subtle background flash on copy success

### 5.7 New: `metric-card` Component
Dashboard metric display. Supports optional trend data but renders cleanly without it.

```html
<div class="bg-white rounded-lg shadow-card p-6 border-l-4 border-{color}-500">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
            <p class="text-2xl font-bold text-gray-900">{{ $value }}</p>
        </div>
        <div class="p-3 bg-{color}-50 rounded-full">
            {{-- inline SVG icon --}}
        </div>
    </div>
    @if (isset($trend) && $trend !== null)
        <div class="mt-2 text-xs text-gray-500">{{ $trend }}</div>
    @endif
</div>
```

### 5.8 New: `tooltip` Component
Alpine.js + absolute positioned div for hover tooltips on icons and truncated text.

---

## 6. Page-by-Page Redesign

### 6.1 Layout — `layouts/navigation.blade.php`
**Keep top navigation.** No sidebar.

Changes:
- Sticky positioning with backdrop blur (`backdrop-blur-sm bg-white/80`)
- Subtle bottom border
- Refined logo/brand area
- Animated active underline/indicator on current nav link
- User initials avatar circle + dropdown
- Responsive mobile hamburger menu
- No notification bell. No fake features.

### 6.2 Layout — `layouts/app.blade.php`
Changes:
- Page transition wrapper (Alpine `x-transition` on main content)
- Improved responsive padding

### 6.3 Layout — `layouts/guest.blade.php`
Changes:
- Subtle gradient background (brand-50 to white)
- Floating brand logo above card
- Polished card with better shadow

### 6.4 Dashboard — `dashboard.blade.php`
**Use only existing backend data.** Do not invent metrics.

Structure:
1. **Header row** — Personalized greeting: "Good morning, {first name}"
2. **Quick actions** — Create Offer, Create Campaign (keep existing logic)
3. **Inventory mini-summary** — Current overview (offer count, campaign count, active campaigns)
4. **Activity period control** — Redesigned period filter (keep existing `<select>` logic, style as segmented control or improved dropdown)
5. **Metric card grid** — 5 cards using real data:
   - Clicks (`click_count`)
   - Conversions (`conversion_count`)
   - Revenue (`revenue`) — with "Approved only" note
   - Expenses (`total_expenses`)
   - Profit (`profit`) — with red color when negative
6. **Recent Offers table** — Existing data, premium styling
7. **Recent Campaigns table** — Existing data, premium styling
8. **Empty state** — When no data exists

**NOT added:** pending review count, trend percentages, growth values, vs previous period.

### 6.5 Offers Index — `offers/index.blade.php`
Changes:
- Premium table with row hover effects
- Payout styled as bold/colored number
- Improved empty state
- Refined search/filter area

### 6.6 Offers Form — `offers/partials/form.blade.php`
Changes:
- Group fields into card sections
- Improve status select styling
- Better input focus states
- Keep all existing fields only (name, destination_url, payout, status, description)

### 6.7 Campaigns Index — `campaigns/index.blade.php`
Changes:
- Premium table with hover states
- Strong status badge presentation
- Improved empty state
- One excellent responsive table (no grid/list toggle)

### 6.8 Campaign Form — `campaigns/partials/form.blade.php`
**Use real fields only:**
- Offer (select, hidden on edit)
- Campaign Name (text)
- Traffic Source (text)
- Budget (number)

Changes:
- Better visual grouping
- Budget input with currency symbol prefix
- Improved input styling
- No fake fields (no description, no performance metrics)

### 6.9 Campaign Show — `campaigns/show.blade.php` (HEAVIEST)
Break into tabbed sections using Alpine.js:

```
[Overview]  [Tracking]  [Expenses]  [Conversions]  [AI Analysis]
```

**Requirements:**
- Accessible tab buttons (role="tablist", aria-selected)
- Keyboard-friendly (arrow keys where practical)
- Clear active state
- Smooth but subtle tab transition
- No page reload between tabs
- Default: Overview

**Tab: Overview**
- Campaign name, status badge, linked offer
- Traffic source, budget, created date
- Status action buttons with proper colors:
  - Activate: `bg-emerald-600` (green)
  - Suspend: `bg-amber-500` (amber)
- Confirmation modals for status transitions
- Landing page URL display

**Tab: Tracking**
- Tracking URLs per campaign (generated via `storeTrackingLink`)
- Copy-to-clipboard with enhanced feedback
- Generate link button (when no links exist and campaign is active)
- No tracking pixel/script/embed (not implemented)

**Tab: Expenses**
- Expense list fetched from API (`GET /api/v1/campaigns/{campaign}/expenses`)
- Expense cards with date, description, amount
- Total expenses summary
- Add/edit/delete expense forms (Alpine.js + API calls)
- CSRF token required for API calls

**Tab: Conversions**
- Conversions fetched from campaign data
- Status badges (pending/approved/rejected)
- Approve/Reject buttons for pending conversions (Alpine.js + API calls)
- No reason/comment field (API accepts none)

**Tab: AI Analysis**
- AI analysis fetched from API (`GET /api/v1/offers/{offer}/analysis`)
- Trigger analysis button (`POST /api/v1/offers/{offer}/analyze`)
- Score emphasis, strengths/weaknesses/recommendations
- Stale indicator when analysis is old
- Processing/loading state
- Failed state

### 6.10 Profile — `profile/edit.blade.php`
Changes:
- Desktop 2-column layout:
  - Main: Profile Information + Password
  - Secondary: Account summary + Danger Zone
- User initials avatar at top
- Danger zone: red-tinted background, warning icon
- "Last updated" timestamp using `User.updated_at` (real data only)
- No invented settings

### 6.11 Auth Pages
Changes (all 6 pages):
- Brand logo above form
- Product tagline (e.g., "CPA performance tracking, simplified")
- Subtle gradient/pattern background
- Polished card with better hierarchy
- Better page titles ("Welcome back", "Create your account")
- No fake testimonials, customer counts, ratings, or social proof

---

## 7. Conversion Review UI

### 7.1 API Contract
- `POST /api/v1/campaigns/{campaign}/conversions/{conversion}/approve` — no request body
- `POST /api/v1/campaigns/{campaign}/conversions/{conversion}/reject` — no request body
- Both expect Sanctum SPA auth (same-origin cookies + CSRF)
- Response: 200 on success, 409 on invalid transition

### 7.2 UI Implementation
**Location:** Campaign show → Conversions tab

**Pending conversions:** Show Approve (green) / Reject (red) buttons
**Click:** Opens confirmation modal (no reason field — API accepts none)
**Submit:** POST to API endpoint via Alpine.js `fetch()` with:
- `credentials: 'same-origin'`
- `X-Requested-With: XMLHttpRequest`
- `X-XSRF-TOKEN` from cookie (or `csrf-token` meta)
- `Accept: application/json`
**On success:** Update row status badge in-place
**On error (409):** Show "Conversion already reviewed" message

**No review reason field.** No moderation metadata. No comment field.

---

## 8. Campaign Tabs Integration Details

### 8.1 Alpine.js Tab Component
```html
<div x-data="{ activeTab: 'overview' }">
    <div role="tablist" class="flex border-b border-gray-200">
        <button role="tab" :aria-selected="activeTab === 'overview'" @click="activeTab = 'overview'"
            :class="activeTab === 'overview' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500'">
            Overview
        </button>
        {{-- ... other tabs --}}
    </div>
    <div x-show="activeTab === 'overview'" role="tabpanel">...</div>
    <div x-show="activeTab === 'tracking'" role="tabpanel">...</div>
    <div x-show="activeTab === 'expenses'" role="tabpanel">...</div>
    <div x-show="activeTab === 'conversions'" role="tabpanel">...</div>
    <div x-show="activeTab === 'ai'" role="tabpanel">...</div>
</div>
```

### 8.2 API-Driven Tabs (Expenses, Conversions, AI)
- Fetch data on tab activation (lazy loading) using Alpine.js `x-init` or `@click`
- Show loading spinner during fetch
- Show empty state if no data
- Handle API errors gracefully
- All API calls use Sanctum SPA auth pattern

### 8.3 CSRF / Auth Pattern for Alpine + API
```javascript
// Get CSRF token from meta or cookie
const token = document.querySelector('meta[name="csrf-token"]')?.content
    || decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '');

fetch('/api/v1/campaigns/{id}/expenses', {
    credentials: 'same-origin',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': token,
    }
})
```

---

## 9. AI Visual Treatment

- Subtle violet gradient accent on AI section header
- Sparkle icon (inline SVG)
- Score emphasis (large number, color-coded)
- Strengths / Weaknesses / Recommendations as cards
- Stale indicator when analysis timestamp is old
- Processing/loading spinner when analysis is running
- Failed state with retry button
- No changes to AI business logic

---

## 10. Animation & Motion

Approved animations (120-220ms typical duration):

| Animation | Where | Duration |
|---|---|---|
| Page entry fade/translate | Main content area | 150ms |
| Card stagger on Dashboard | Metric cards entrance | 150ms, staggered 50ms |
| Hover elevation | Cards, table rows | 150ms |
| Button press feedback | All buttons | 100ms |
| Dropdown open/close | User dropdown, filters | 150ms |
| Modal fade/scale | Confirmation modals | 200ms |
| Copy success | Tracking URL copy | 150ms |
| Flash message enter/leave | Flash component | 200ms |
| Tab transition | Campaign show tabs | 150ms |
| Pending badge pulse | Status badge (pending) | 2s loop |
| Reduced-motion support | All animations | `prefers-reduced-motion` |

Do not animate every element. No decorative transitions longer than 220ms.

---

## 11. Responsive Approach

| Breakpoint | Layout |
|---|---|
| < 640px (mobile) | Single column, stacked cards, hamburger nav, full-width tables |
| 640-1024px (tablet) | 2-column grids, side-by-side cards |
| > 1024px (desktop) | Full nav visible, 3-4 column grids, full table view, profile 2-column |

---

## 12. Accessibility

- All interactive elements: visible focus rings (`focus:ring-2 focus:ring-brand-500`)
- Color contrast: WCAG AA (4.5:1 body, 3:1 large text)
- Status badges: always include text label alongside color dot
- Form inputs: proper `<label>` associations
- Modal: focus trap, escape to close
- Tab component: `role="tablist"`, `role="tab"`, `role="tabpanel"`, `aria-selected`, `aria-controls`
- Skip-to-content link in layout

---

## 13. Implementation Phases

Each phase must be tested before moving to the next.

### Phase 1: Foundation + Design Tokens + Shared Components
- Update `tailwind.config.js` with semantic colors, border-radius tokens
- Update `resources/css/app.css` with utility classes, reduced-motion support
- Redesign `primary-button` (brand color, readable labels)
- Add `ghost-button` component
- Upgrade `status-badge` (7 states)
- Upgrade `empty-state` (better visuals)
- Upgrade `confirm-button` (Alpine modal)
- Upgrade `search-input` (clear button)
- Upgrade `tracking-url` (copy feedback)
- Create `metric-card` component
- Create `tooltip` component

### Phase 2: Premium Top Navigation + Layouts
- Redesign `layouts/navigation.blade.php` (sticky, backdrop-blur, active indicator, initials avatar, mobile menu)
- Redesign `layouts/app.blade.php` (page transition wrapper)
- Redesign `layouts/guest.blade.php` (gradient background, logo)

### Phase 3: Dashboard
- Redesign `dashboard.blade.php` using real backend data only
- Personalized greeting
- Metric card grid (clicks, conversions, revenue, expenses, profit)
- Improved inventory summary
- Improved period filter styling
- Premium recent offers/campaigns tables
- Improved empty state

### Phase 4: Offers
- Premium offers index table with hover states, payout styling
- Refined offers create/edit form
- Improved empty state

### Phase 5: Campaigns Workspace
- Premium campaigns index table
- Campaign show tabbed layout (Alpine.js)
- Overview tab with status actions + confirmation modals
- Tracking tab with existing tracking links
- Expenses tab (API-driven)
- Conversions tab with approve/reject UI (API-driven)
- AI Analysis tab (API-driven)
- Campaign create/edit form (real fields only)

### Phase 6: Profile + Auth
- Profile 2-column layout with avatar, danger zone styling
- Auth pages brand treatment (logo, tagline, gradient)

### Phase 7: Motion + Responsive + Accessibility + Consistency
- Implement approved animations
- Responsive testing across breakpoints
- Accessibility audit (focus rings, contrast, ARIA)
- Cross-page consistency check

### Phase 8: Final Presentation Polish
- Screenshot review
- Edge case cleanup
- Final visual consistency pass

---

## 14. Risk Considerations

| Risk | Mitigation |
|---|---|
| Breaking existing functionality | Test after each phase, keep Blade component props backward-compatible |
| Alpine.js API integration complexity | Test Sanctum SPA auth pattern first in Phase 5 |
| Performance with animations | Use `will-change` sparingly, respect `prefers-reduced-motion` |
| Mobile navigation | Test hamburger menu touch interactions |
| Tab content loading | Lazy-fetch API data only on tab activation |

---

## 15. Success Criteria

- [ ] All pages render correctly at 320px, 768px, 1280px, 1920px
- [ ] Brand color consistently used across buttons, badges, links
- [ ] Conversion review UI functional (approve/reject from campaign show)
- [ ] All status badges show 7 states (draft/active/suspended/archived/pending/approved/rejected)
- [ ] Page transitions smooth (< 220ms)
- [ ] No accessibility regressions (focus rings, contrast, labels, ARIA)
- [ ] Existing tests still pass (Pest + Newman)
- [ ] No backend changes made

---

## 16. File Change Manifest

### Config (2 files — Modify)
1. `tailwind.config.js`
2. `resources/css/app.css`

### Layouts (3 files — Modify)
3. `resources/views/layouts/app.blade.php`
4. `resources/views/layouts/guest.blade.php`
5. `resources/views/layouts/navigation.blade.php`

### Pages (9 files — Modify)
6. `resources/views/dashboard.blade.php`
7. `resources/views/offers/index.blade.php`
8. `resources/views/offers/create.blade.php`
9. `resources/views/offers/edit.blade.php`
10. `resources/views/campaigns/index.blade.php`
11. `resources/views/campaigns/show.blade.php`
12. `resources/views/campaigns/create.blade.php`
13. `resources/views/campaigns/edit.blade.php`
14. `resources/views/profile/edit.blade.php`

### Partials (4 files — Modify)
15. `resources/views/offers/partials/form.blade.php`
16. `resources/views/campaigns/partials/form.blade.php`
17. `resources/views/profile/partials/update-profile-information-form.blade.php`
18. `resources/views/profile/partials/delete-user-form.blade.php`

### Components (14 files — Modify)
19. `resources/views/components/primary-button.blade.php`
20. `resources/views/components/secondary-button.blade.php`
21. `resources/views/components/status-badge.blade.php`
22. `resources/views/components/empty-state.blade.php`
23. `resources/views/components/confirm-button.blade.php`
24. `resources/views/components/search-input.blade.php`
25. `resources/views/components/tracking-url.blade.php`
26. `resources/views/components/page-header.blade.php`
27. `resources/views/components/nav-link.blade.php`
28. `resources/views/components/text-input.blade.php`
29. `resources/views/components/input-label.blade.php`
30. `resources/views/components/flash-message.blade.php`
31. `resources/views/components/modal.blade.php`
32. `resources/views/components/dropdown.blade.php`

### Components (2 files — Create)
33. `resources/views/components/metric-card.blade.php`
34. `resources/views/components/tooltip.blade.php`

### Auth (6 files — Modify)
35. `resources/views/auth/login.blade.php`
36. `resources/views/auth/register.blade.php`
37. `resources/views/auth/forgot-password.blade.php`
38. `resources/views/auth/reset-password.blade.php`
39. `resources/views/auth/verify-email.blade.php`
40. `resources/views/auth/confirm-password.blade.php`

**Totals:**
- **40 files changed**
- **2 created** (metric-card, tooltip)
- **38 modified**

---

## 17. Backend Freeze Confirmation

This redesign phase MUST NOT change:
- Policies
- Actions
- Models
- Database schema
- Migrations
- API contracts
- Dashboard formulas
- Conversion transition rules
- Tracking rules
- Expense rules
- AI rules

If UI work exposes a real backend blocker: STOP and report it. Do not silently modify backend behavior.

---

## 18. Reference Design Contract

**Established:** Phase 4 calibration pass
**Source of truth** for all future phases (Campaigns, Conversions, AI, Profile, Auth)

### 18.1 Visual References

| Reference | Weight | Use For |
|-----------|--------|---------|
| **Dub** (primary) | 75% | Information density, tables, forms, filters, whitespace, compact SaaS controls, borders, restrained typography, surface hierarchy |
| **Rewardful** (secondary) | 15% | Friendly softness, semantic status colors, comfortable whitespace, rounded state treatments |
| **CPAFlow brand** (identity) | 10% | Indigo/violet brand, own visual personality |

**Do NOT copy either product literally.** Do NOT adopt Dub's blue (`#2563EB`) as CPAFlow's brand.

### 18.2 Canvas & Surfaces

| Token | Value |
|-------|-------|
| Canvas | `#F8FAFC` / Tailwind `slate-50` equivalent |
| Surface | `#FFFFFF` |
| Border | `#E5E7EB` / neutral-200 |
| Primary text | `#171717` / deep neutral |
| Secondary text | neutral-500 / neutral-600 |

### 18.3 Brand Colors

| Role | Value |
|------|-------|
| Primary | brand-600 (`#4F46E5`) |
| Hover | brand-700 |
| Soft surface | brand-50 / brand-100 |
| Success | emerald |
| Warning | amber |
| Danger | red |
| Info | blue/indigo (only when semantically appropriate) |

### 18.4 Typography

- **Font:** Figtree (do NOT switch to system-ui)
- Page title: 24–28px, 600/700 weight
- Section title: 16–18px, 600
- Body: 14px, 400/500
- Muted/help: 12–14px
- Table labels: 12–13px, 500/600
- Buttons: 14px, 500/600
- Sentence case. Avoid excessive uppercase.

### 18.5 Spacing

- Base rhythm: 8px
- Preferred values: 4, 8, 12, 16, 24, 32, 40, 48
- Page sections: 24–32px vertical separation
- Card padding: 20–24px
- Form field gap: 20–24px
- Toolbar gaps: 8–12px
- Table cell padding: 12–16px

### 18.6 Border Radius

| Element | Radius |
|---------|--------|
| Inputs | 8px (`rounded-lg`) |
| Buttons | 8–10px (`rounded-lg`) |
| Cards / table containers | 12px (`rounded-card`) |
| Dropdowns | 10–12px |
| Badges | 9999px (pill) |

### 18.7 Shadows / Elevation

- Most surfaces: border + very subtle shadow (`0 1px 2px rgba(0,0,0,.04)`)
- Stronger shadows only for: dropdowns, modals, temporary overlays
- Table/form cards: white surface + neutral border + subtle shadow

### 18.8 Buttons

- Compact, product-focused, ~36–40px height
- Primary: brand-600, white text, 8–10px radius
- Secondary: white, neutral border, dark text
- Ghost: transparent, neutral text, soft hover
- Danger: subtle red treatment, not oversized
- No giant CTA buttons. No excessive letter spacing.

### 18.9 Density

- UI density: compact / professional SaaS
- Table rows: comfortable but not oversized
- Form inputs: compact SaaS height (~40–44px)
- Empty states: clear icon, short title, concise explanation, one CTA. Not giant or overly decorative.

### 18.10 Motion

- Duration: 120–180ms
- Allowed: row hover, button press, modal, dropdown, page entry, copy feedback
- No decorative animation added just because references have motion

### 18.11 Status Badges

- Compact pill shape
- Soft semantic backgrounds (Rewardful-inspired softness)
- Active: emerald. Draft: neutral. Suspended: amber. Archived: neutral muted.
- Keep badges small, not overly large pills.
