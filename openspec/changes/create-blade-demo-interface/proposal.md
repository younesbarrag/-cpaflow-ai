# Proposal: Create Blade Demo Interface (KAN-31)

## Problem

KAN-8 through KAN-15 have delivered a complete backend: authentication, profile management, Offers CRUD with status lifecycle and archival, Campaigns CRUD with draft/active/suspended lifecycle, TrackingLink generation, and public tracking redirect with click recording. The only accessible frontend is the default Laravel Breeze scaffold — a generic dashboard page, basic login/register, and a profile editor. There is no interface to manage Offers, Campaigns, or TrackingLinks. The application cannot be demonstrated as a functional CPA affiliate platform without one.

## Objective

Plan the first complete Blade demonstration interface that showcases all backend features implemented through KAN-15. The UI must reuse existing Actions, Policies, Form Requests, and Eloquent models — not duplicate business logic in Blade Controllers.

## Scope

### In Scope

1. **Design system** — tokens, components, typography, spacing, colors, surface hierarchy, badges, tables, forms, buttons.
2. **Application shell** — responsive layout with top bar, sidebar navigation, logo, user menu, logout, flash notifications, mobile nav.
3. **Authentication screens** — polished login, register, forgot-password, reset-password, verify-email, confirm-password using existing Breeze routes.
4. **Profile screen** — grouped sections (profile info, password, delete account) using existing `ProfileController` and `ProfileUpdateRequest`.
5. **Offers CRUD** — index with search/filter, create form, edit form, status badges, empty state, ownership isolation.
6. **Campaigns CRUD** — index, create form (eligible Offers dropdown), edit form, lifecycle actions (activate/suspend), ownership isolation.
7. **Tracking-link generation** — generate button on active Campaigns, display generated URL, copy-to-clipboard.
8. **Public tracking flow** — evaluator can generate, copy, open, and observe redirect.
9. **UX states** — empty, loading, validation, unauthorized/forbidden, success flash, error handling.
10. **Responsive design** — desktop, tablet, mobile.
11. **Accessibility** — semantic HTML, labels, keyboard nav, focus states, contrast.
12. **JavaScript** — minimal footprint: mobile nav toggle, copy-to-clipboard, flash dismiss, confirmation dialogs.
13. **Automated tests** — Pest feature tests for all web routes.
14. **Manual smoke test plan** — browser verification checklist.

### Exclusions

- Conversions UI
- Expenses UI
- Dashboard analytics
- Period filters
- Click analytics / unique visitors
- AI analysis / generated content
- Admin UI
- Docker / Azure deployment
- Frontend for stories not yet implemented (KAN-16+)

## Current State

**Branch:** `feature/KAN-31-blade-demo-frontend`

**Frontend stack discovered:**

| Component | Status | Evidence |
|-----------|--------|----------|
| Laravel 13.8 | Installed | `composer.json` |
| PHP ^8.3 | Installed | `composer.json` |
| Tailwind CSS ^3.1 | Installed | `package.json`, `tailwind.config.js` |
| @tailwindcss/forms | Installed | `package.json` |
| Alpine.js ^3.4.2 | Installed | `package.json`, `resources/js/app.js` |
| Vite ^8.0 | Installed | `package.json`, `vite.config.js` |
| laravel-vite-plugin ^3.1 | Installed | `package.json` |
| Figtree font | Configured | `tailwind.config.js`, `layouts/app.blade.php` |

**Blade foundation discovered:**

| Component | Status | Evidence |
|-----------|--------|----------|
| Breeze Blade | Installed | `routes/auth.php`, auth views, profile views |
| `x-app-layout` | Exists | `layouts/app.blade.php` — component with `$header` slot and `$slot` |
| `x-guest-layout` | Exists | `layouts/guest.blade.php` — centered card layout |
| `x-application-logo` | Exists | `components/application-logo.blade.php` — Laravel SVG logo |
| `x-nav-link` | Exists | `components/nav-link.blade.php` — indigo active state |
| `x-responsive-nav-link` | Exists | `components/responsive-nav-link.blade.php` |
| `x-dropdown` / `x-dropdown-link` | Exists | Used in navigation |
| `x-primary-button` | Exists | Gray-800 button |
| `x-secondary-button` | Exists | Available |
| `x-danger-button` | Exists | Available |
| `x-text-input` | Exists | Styled input |
| `x-input-label` | Exists | Form label |
| `x-input-error` | Exists | Validation error display |
| `x-auth-session-status` | Exists | Session flash status |
| `x-modal` | Exists | Available |
| Navigation | Breeze default | Only "Dashboard" link, hamburger menu |
| Dashboard view | Default Breeze | "You're logged in!" placeholder |
| Profile view | Breeze default | Three partials: info, password, delete |

**Auth routes confirmed:** login, register, forgot-password, reset-password, verify-email, confirm-password, logout — all in `routes/auth.php`.

**Existing web routes:** `/` (welcome), `/dashboard` (auth+verified), `/profile` (edit/update/destroy), `/t/{code}` (public tracking redirect).

**Existing API routes (not affected):** Full `/api/v1` CRUD for offers, campaigns, tracking-links.

**Existing backend Actions (reusable):**

| Action | Purpose |
|--------|---------|
| `CreateOfferAction` | Create offer with ownership |
| `UpdateOfferAction` | Update offer fields |
| `ArchiveOfferAction` | Archive offer |
| `CreateCampaignAction` | Create campaign under offer |
| `UpdateCampaignAction` | Update campaign fields |
| `ActivateCampaignAction` | Draft/suspended → active |
| `SuspendCampaignAction` | Active → suspended |
| `GenerateTrackingLinkAction` | Generate unique tracking code |
| `RecordTrackingClickAction` | Record click (public) |
| `UpdateUserProfileAction` | Update name/email |

**Existing Policies (reusable):**

| Policy | Methods |
|--------|---------|
| `OfferPolicy` | `update`, `archive`, `createCampaign` — all ownership-based |
| `CampaignPolicy` | `view`, `update`, `activate`, `suspend`, `generateTrackingLink` — ownership derived through Offer |

## Risks

| Risk | Mitigation |
|------|------------|
| Blade Controllers could duplicate API logic | Enforce Action/Policy reuse; Controllers are thin coordinators only |
| N+1 queries in Blade templates | eager-load relationships in Controllers; test query count |
| Tailwind CSS bundle size | Purge unused classes via Vite; no runtime CSS framework |
| Alpine.js scope creep | Restrict to mobile nav, copy, dismiss — no SPA patterns |
| Existing Breeze views could conflict | Extend or replace selectively; keep Breeze auth routes intact |
| CSRF on all forms | `@csrf` on every form; verify in tests |
| Mobile table overflow | Use card layout or horizontal scroll with justification |

## Acceptance Criteria

1. Authenticated user sees a professional application shell with navigation.
2. User can log in, register, and log out through polished Blade screens.
3. User can view and edit profile with validation feedback.
4. User can create, list, edit, and archive Offers with ownership isolation.
5. User can create Campaigns with eligible Offer selection.
6. User can activate and suspend Campaigns with confirmation.
7. User can generate TrackingLinks for active Campaigns and copy the URL.
8. User can open the public tracking link and observe the redirect.
9. All mutations show flash success messages.
10. Validation errors render inline on forms.
11. Empty states display when no data exists.
12. Foreign resources return 403/404 appropriately.
13. All Pest feature tests pass.
14. Existing API tests remain unaffected.
15. `npm run build` succeeds.
16. `vendor/bin/pint --test` passes.
