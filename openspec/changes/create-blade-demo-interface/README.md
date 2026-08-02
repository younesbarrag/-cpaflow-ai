# KAN-31: Create Blade Demo Interface

## Status

Planning complete. Awaiting implementation approval.

## Story

As a CPAFlow evaluator, I want a complete Blade demonstration interface so I can see all backend features (auth, profile, offers, campaigns, tracking links, public redirect) working together in a professional SaaS dashboard.

Branch: `feature/KAN-31-blade-demo-frontend`

## Package

- `proposal.md`: problem, objectives, scope, discovered stack, risks, and acceptance criteria.
- `design.md`: design system, application shell, exact routes, Controller strategy, business logic reuse, responsive layout, accessibility, JavaScript, Blade components, test scenarios, and manual verification plan.
- `spec.md`: normative requirements (17 requirement groups), scenarios, HTTP behavior, and acceptance criteria mapping.
- `tasks.md`: 143 independently verifiable, unchecked implementation tasks.
- `README.md`: planning summary and key decisions.

## Key Decisions

| Topic | Decision |
|---|---|
| Frontend stack | Tailwind CSS ^3.1 + Alpine.js ^3.4.2 (already installed, no new dependencies) |
| Layout | Top bar navigation (no sidebar); hamburger menu on mobile |
| Navigation items | Overview, Offers, Campaigns (Profile via user dropdown) |
| Business logic reuse | Web Controllers delegate to existing Actions and Policies; no duplication |
| Form validation | New web-specific Form Requests mirroring API rules |
| Tracking-link UX | Generate button on campaign detail page; copy-to-clipboard via Alpine.js |
| Mobile tables | Card layout below `sm:` breakpoint |
| JavaScript | Alpine.js only; no React/Vue; no SPA patterns |
| Design tokens | Brand color palette + card shadows added to Tailwind config |
| Status badges | Color + text label (not color-only) for accessibility |
| Flash messages | Alpine.js dismissable with 5-second auto-hide |
| Offer details page | Not created (index + edit is sufficient for demo) |
| Campaign detail page | Created to host tracking-link generation |
| Conversions/expenses/analytics | Explicitly excluded until backend implemented |

## Discovered Frontend Stack

| Component | Version | Evidence |
|-----------|---------|----------|
| Tailwind CSS | ^3.1 | `package.json`, `tailwind.config.js` |
| @tailwindcss/forms | ^0.5.2 | `package.json` |
| Alpine.js | ^3.4.2 | `package.json`, `resources/js/app.js` |
| Vite | ^8.0 | `package.json`, `vite.config.js` |
| laravel-vite-plugin | ^3.1 | `package.json` |
| Figtree font | — | `tailwind.config.js`, `layouts/app.blade.php` |
| Laravel Breeze (Blade) | Installed | Auth routes, views, profile views |

## Discovered Blade Foundation

| Component | File |
|-----------|------|
| `<x-app-layout>` | `layouts/app.blade.php` — `$header` slot + `$slot` |
| `<x-guest-layout>` | `layouts/guest.blade.php` — centered card |
| `<x-nav-link>` | `components/nav-link.blade.php` |
| `<x-responsive-nav-link>` | `components/responsive-nav-link.blade.php` |
| `<x-dropdown>` / `<x-dropdown-link>` | Used in navigation |
| `<x-primary-button>` | Gray-800 button |
| `<x-secondary-button>` | Available |
| `<x-danger-button>` | Available |
| `<x-text-input>` | Styled input |
| `<x-input-label>` | Form label |
| `<x-input-error>` | Validation error |
| `<x-modal>` | Available |
| `<x-application-logo>` | Laravel SVG logo |

## Planned Routes

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/offers` | `offers.index` |
| GET | `/offers/create` | `offers.create` |
| POST | `/offers` | `offers.store` |
| GET | `/offers/{offer}/edit` | `offers.edit` |
| PATCH | `/offers/{offer}` | `offers.update` |
| POST | `/offers/{offer}/archive` | `offers.archive` |
| GET | `/campaigns` | `campaigns.index` |
| GET | `/campaigns/create` | `campaigns.create` |
| POST | `/campaigns` | `campaigns.store` |
| GET | `/campaigns/{campaign}` | `campaigns.show` |
| GET | `/campaigns/{campaign}/edit` | `campaigns.edit` |
| PATCH | `/campaigns/{campaign}` | `campaigns.update` |
| POST | `/campaigns/{campaign}/activate` | `campaigns.activate` |
| POST | `/campaigns/{campaign}/suspend` | `campaigns.suspend` |
| POST | `/campaigns/{campaign}/tracking-links` | `campaigns.tracking-links.store` |

## Planned Controllers

| Controller | Methods |
|-----------|---------|
| `OfferController` | `index`, `create`, `store`, `edit`, `update`, `archive` |
| `CampaignController` | `index`, `create`, `store`, `show`, `edit`, `update`, `activate`, `suspend`, `storeTrackingLink` |

## Planned Components

| Component | Purpose |
|-----------|---------|
| `<x-page-header>` | Page title + optional action button |
| `<x-empty-state>` | Empty list placeholder |
| `<x-status-badge>` | Status indicator with color + text |
| `<x-flash-message>` | Flash notification with dismiss |
| `<x-search-input>` | Search/filter input |
| `<x-confirm-button>` | POST button with JS confirm |
| `<x-tracking-url>` | Read-only URL with copy button |

## Test Coverage Summary

Planned Pest coverage (41 scenarios) includes guest redirect, authenticated shell, Offer ownership isolation, Offer CRUD, Offer validation, Offer archive, Campaign ownership, Campaign CRUD, Campaign lifecycle, domain transition errors, TrackingLink generation, TrackingLink display, CSRF protection, Profile rendering, Profile validation, Profile update, flash messages, navigation state, N+1 query prevention, existing API regression, and existing KAN-14/KAN-15 regression.

## Scope Exclusions

Conversions UI, expenses UI, dashboard analytics, period filters, click analytics, unique visitors, AI analysis, generated captions/hooks, admin UI, Docker, Azure deployment, and frontend for stories not yet implemented (KAN-16+) are explicitly excluded.

## Implementation Verification Commands

```bash
php artisan test
php artisan test tests/Feature/Web/
vendor/bin/pint --test
npm run build
php artisan route:list
```

## Approval Points

1. No new Composer or npm dependencies are added.
2. Web Controllers reuse existing Actions and Policies — no business logic duplication.
3. Breeze auth routes and views are preserved, not replaced.
4. Alpine.js is the only JavaScript framework.
5. Design tokens extend Tailwind config — no separate CSS framework.
6. Offer details page is not created (index + edit is sufficient).
7. Campaign detail page is created for tracking-link generation.
8. Future pages (conversions, expenses, analytics, AI) are deferred.

Approval of this package authorizes planning decisions only unless implementation is separately requested.
