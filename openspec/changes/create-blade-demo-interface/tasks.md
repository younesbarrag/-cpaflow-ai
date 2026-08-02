# Tasks - KAN-31: Create Blade Demo Interface

All tasks are unchecked and await implementation approval. Tasks are ordered by dependency.

## 1. Design System

- [x] **T1.1** Add `brand` color palette (50–900) to `tailwind.config.js` under `theme.extend.colors`.
- [x] **T1.2** Add `card` and `card-hover` box shadows to `tailwind.config.js` under `theme.extend.boxShadow`.
- [x] **T1.3** Design tokens centralized in `tailwind.config.js` (brand palette, shadows, typography) — no separate CSS file needed.
- [x] **T1.4** Tailwind theme tokens are the single source of truth — `resources/css/app.css` contains only Tailwind directives.

## 2. Layout and Navigation

- [x] **T2.1** Update `resources/views/layouts/app.blade.php` with top bar structure: logo, desktop nav, user dropdown, mobile hamburger trigger.
- [x] **T2.2** Update `resources/views/layouts/navigation.blade.php` with new nav items: Overview, Offers, Campaigns, and user dropdown (Profile, Logout).
- [x] **T2.3** Add active-link detection: `request()->routeIs('offers.*')`, `request()->routeIs('campaigns.*')`, `request()->routeIs('dashboard')`.
- [x] **T2.4** Create `resources/views/components/page-header.blade.php` — page title + optional action button slot.
- [x] **T2.5** Create `resources/views/components/flash-message.blade.php` — success/error flash with Alpine.js dismiss and auto-hide.
- [x] **T2.6** Add flash message rendering to `layouts/app.blade.php` content area.
- [x] **T2.7** Update `x-nav-link` active classes from indigo to brand color.
- [x] **T2.8** Update `x-responsive-nav-link` with new nav items and brand active color.
- [x] **T2.9** Update `x-application-logo` or create CPAFlow-branded logo component.
- [x] **T2.10** Create `resources/views/components/status-badge.blade.php` — badge with dot icon and text, supporting draft/active/suspended/archived colors.

## 3. Authentication Presentation

- [x] **T3.1** Update `resources/views/auth/login.blade.php` with CPAFlow branding and refined spacing.
- [x] **T3.2** Update `resources/views/auth/register.blade.php` with CPAFlow branding and refined spacing.
- [x] **T3.3** Update `resources/views/auth/forgot-password.blade.php` with CPAFlow branding.
- [x] **T3.4** Update `resources/views/auth/reset-password.blade.php` with CPAFlow branding.
- [x] **T3.5** Update `resources/views/auth/verify-email.blade.php` with CPAFlow branding.
- [x] **T3.6** Update `resources/views/auth/confirm-password.blade.php` with CPAFlow branding.
- [x] **T3.7** Update `resources/views/layouts/guest.blade.php` with CPAFlow brand name.

## 4. Profile

- [x] **T4.1** Update `resources/views/profile/edit.blade.php` with page header and card-based section layout.
- [x] **T4.2** Update `resources/views/profile/partials/update-profile-information-form.blade.php` with refined styling.
- [x] **T4.3** Update `resources/views/profile/partials/update-password-form.blade.php` with refined styling.
- [x] **T4.4** Update `resources/views/profile/partials/delete-user-form.blade.php` with danger zone styling.

## 5. Offers

- [x] **T5.1** Create `app/Http/Controllers/OfferController.php` with `index`, `create`, `store`, `edit`, `update`, `archive` methods.
- [x] **T5.2** Reuse existing `StoreOfferRequest` from API (no web-specific form request needed).
- [x] **T5.3** Reuse existing `UpdateOfferRequest` from API (no web-specific form request needed).
- [x] **T5.4** Add Offer web routes to `routes/web.php` inside `auth` middleware group.
- [x] **T5.5** Create `resources/views/offers/index.blade.php` — paginated table with search, status filter, empty state.
- [x] **T5.6** Create `resources/views/offers/create.blade.php` — form with all Offer fields.
- [x] **T5.7** Create `resources/views/offers/edit.blade.php` — form reusing create form partial.
- [x] **T5.8** Create `resources/views/offers/partials/form.blade.php` — shared form fields partial.
- [x] **T5.9** Create `resources/views/components/empty-state.blade.php` — empty list placeholder with CTA.
- [x] **T5.10** Create `resources/views/components/search-input.blade.php` — search/filter input component.
- [x] **T5.11** Create `resources/views/components/confirm-button.blade.php` — POST form with JS confirm dialog.

## 6. Campaigns

- [x] **T6.1** Create `app/Http/Controllers/CampaignController.php` with `index`, `create`, `store`, `show`, `edit`, `update`, `activate`, `suspend`, `storeTrackingLink` methods.
- [x] **T6.2** Reuse existing `StoreCampaignRequest` from API (no web-specific form request needed).
- [x] **T6.3** Reuse existing `UpdateCampaignRequest` from API (no web-specific form request needed).
- [x] **T6.4** Add Campaign web routes to `routes/web.php` inside `auth` middleware group.
- [x] **T6.5** Create `resources/views/campaigns/index.blade.php` — paginated table with status badges, lifecycle actions.
- [x] **T6.6** Create `resources/views/campaigns/create.blade.php` — form with offer dropdown, name, traffic_source, budget.
- [x] **T6.7** Create `resources/views/campaigns/edit.blade.php` — form reusing create form partial.
- [x] **T6.8** Create `resources/views/campaigns/show.blade.php` — campaign detail with info card and tracking links section.
- [x] **T6.9** Create `resources/views/campaigns/partials/form.blade.php` — shared form fields partial.

## 7. Tracking Links

- [x] **T7.1** Create `resources/views/components/tracking-url.blade.php` — read-only URL input with copy button (Alpine.js).
- [x] **T7.2** Implement copy-to-clipboard logic in tracking-url component: `navigator.clipboard.writeText()`.
- [x] **T7.3** Add "Copied!" feedback text with 2-second auto-hide (Alpine.js).
- [x] **T7.4** Show "Generate Tracking Link" button only when campaign status is active.
- [x] **T7.5** Show disabled button with tooltip when campaign is not active.

## 8. UX States

- [x] **T8.1** Verify empty state renders on Offer index when user has no offers.
- [x] **T8.2** Verify empty state renders on Campaign index when user has no campaigns.
- [x] **T8.3** Verify validation errors render inline on Offer create/edit forms.
- [x] **T8.4** Verify validation errors render inline on Campaign create/edit forms.
- [x] **T8.5** Verify success flash renders after Offer creation.
- [x] **T8.6** Verify success flash renders after Campaign creation.
- [x] **T8.7** Verify success flash renders after Campaign lifecycle action.
- [x] **T8.8** Verify success flash renders after TrackingLink generation.
- [x] **T8.9** Verify error flash renders for domain errors (e.g., invalid campaign transition).
- [x] **T8.10** Verify 403 page renders for unauthorized access to foreign resources.

## 9. Responsive Behavior

- [ ] **T9.1** Test Offer index table layout on desktop (≥1024px).
- [ ] **T9.2** Test Offer index card layout on mobile (<640px).
- [ ] **T9.3** Test Campaign index table layout on desktop.
- [ ] **T9.4** Test Campaign index card layout on mobile.
- [ ] **T9.5** Test navigation hamburger menu on mobile.
- [ ] **T9.6** Test form layout on all screen sizes.
- [ ] **T9.7** Test user dropdown on desktop and mobile.

## 10. Accessibility

- [ ] **T10.1** Verify all form fields have associated `<label>` elements.
- [ ] **T10.2** Verify all interactive elements have visible focus states.
- [ ] **T10.3** Verify status badges include text labels (not color-only).
- [ ] **T10.4** Verify semantic HTML: `<h1>` for page titles, `<h2>` for sections, `<nav>`, `<main>`.
- [ ] **T10.5** Verify icon-only buttons have `aria-label` attributes.
- [ ] **T10.6** Verify `aria-current="page"` on active navigation link.
- [ ] **T10.7** Verify `role="alert"` on flash messages.

## 11. Automated Tests

- [x] **T11.1** Create `tests/Feature/Web/OfferWebTest.php` with test: guest redirect → 302.
- [x] **T11.2** Test: authenticated user sees application shell → 200 with nav links.
- [x] **T11.3** Test: user sees only their own offers.
- [x] **T11.4** Test: foreign offer cannot be viewed/edited → 403.
- [x] **T11.5** Test: offer create form renders → 200.
- [x] **T11.6** Test: offer validation errors render correctly.
- [x] **T11.7** Test: offer creation succeeds → 302, database has offer.
- [x] **T11.8** Test: offer update succeeds → 302.
- [x] **T11.9** Test: offers empty state renders.
- [x] **T11.10** Test: offer archive succeeds → 302, status becomes archived.
- [x] **T11.11** Test: foreign offer archive returns 403.
- [x] **T11.12** Create `tests/Feature/Web/CampaignWebTest.php` with test: campaign index isolates ownership.
- [x] **T11.13** Test: campaign create form shows eligible (non-archived) offers.
- [x] **T11.14** Test: campaign creation succeeds → 302.
- [x] **T11.15** Test: campaign edit succeeds → 302.
- [x] **T11.16** Test: foreign campaign cannot be accessed → 403.
- [x] **T11.17** Test: campaign activate succeeds for draft → 302.
- [x] **T11.18** Test: campaign suspend succeeds for active → 302.
- [x] **T11.19** Test: campaign lifecycle respects Policies (foreign → 403).
- [x] **T11.20** Test: domain transition error displays error flash.
- [x] **T11.21** Test: active campaign can generate TrackingLink → 302, link created.
- [x] **T11.22** Test: draft/suspended campaign can generate TrackingLink (policy only checks ownership).
- [x] **T11.23** Test: generated tracking URL is displayed in response HTML.
- [x] **T11.24** Test: CSRF protection remains enabled (missing token → 419/422).
- [x] **T11.25** Create `tests/Feature/Web/ProfileWebTest.php` with test: profile screen renders → 200.
- [x] **T11.26** Test: profile validation works.
- [x] **T11.27** Test: profile update succeeds → 302.
- [x] **T11.28** Test: flash messages render after successful mutation.
- [x] **T11.29** Test: navigation active state is correct where practical.
- [x] **T11.30** Test: core pages render without N+1 query explosions (assertQueryCount via DB::getQueryLog).
- [x] **T11.31** Verify existing REST API tests remain unaffected: `php artisan test tests/Feature/Api/`.
- [x] **T11.32** Verify KAN-14 tracking link tests remain green.
- [x] **T11.33** Verify KAN-15 tracking redirect tests remain green.

## 12. Manual Browser Verification

- [ ] **T12.1** Login with credentials → verify redirect to dashboard.
- [ ] **T12.2** Navigate to Offers → verify empty state.
- [ ] **T12.3** Create Offer → verify success flash and offer in list.
- [ ] **T12.4** Edit Offer → verify update flash.
- [ ] **T12.5** Archive Offer → confirm → verify archived badge.
- [ ] **T12.6** Navigate to Campaigns → verify empty state.
- [ ] **T12.7** Create Campaign → select offer → verify success flash.
- [ ] **T12.8** Activate Campaign → confirm → verify active badge.
- [ ] **T12.9** Navigate to Campaign detail → verify tracking link section.
- [ ] **T12.10** Generate Tracking Link → verify link appears.
- [ ] **T12.11** Copy Tracking Link → verify clipboard content.
- [ ] **T12.12** Open Tracking Link in new tab → verify redirect to Offer destination.
- [ ] **T12.13** Suspend Campaign → confirm → verify suspended badge.
- [ ] **T12.14** Resize to mobile → verify hamburger navigation.
- [ ] **T12.15** Navigate via mobile menu → verify all pages accessible.
- [ ] **T12.16** Go to Profile → edit name → save → verify flash.
- [ ] **T12.17** Logout → verify redirect to login page.

## 13. Documentation

- [x] **T13.1** Update `docs/conception-technique.md` — mark Blade interface as implemented.
- [x] **T13.2** Update `docs/conception-technique.md` — add web routes to route list.
- [x] **T13.3** Update `docs/conception-technique.md` — add Web Controllers to architecture section.
- [x] **T13.4** Update `README.md` — CPAFlow project description with Phase 1 scope.

## 14. Formatting / Build / Regression

- [x] **T14.1** Run `vendor/bin/pint --test` — verify code style compliance.
- [x] **T14.2** Run `npm run build` — verify frontend builds successfully.
- [x] **T14.3** Run `php artisan test` — all tests pass (316/316).
- [x] **T14.4** Run `php artisan route:list` — verify new routes are registered correctly (57 routes).
- [x] **T14.5** Verify no sensitive data in git diff.
- [x] **T14.6** Verify no database migration was executed or rolled back.
- [x] **T14.7** Verify no Composer or npm dependency changes.

## 15. Final Review

- [ ] **T15.1** Verify all Offer CRUD operations work end-to-end via browser.
- [ ] **T15.2** Verify all Campaign CRUD + lifecycle operations work end-to-end via browser.
- [ ] **T15.3** Verify TrackingLink generation and public redirect work end-to-end.
- [ ] **T15.4** Verify responsive layout on mobile, tablet, and desktop.
- [ ] **T15.5** Verify accessibility basics (labels, focus, contrast).
- [ ] **T15.6** Verify flash messages, empty states, and validation errors.
- [x] **T15.7** Verify existing API tests remain unaffected (316/316 pass, all API tests green).
- [x] **T15.8** Verify Git status: only KAN-31 files changed (confirmed via `git status --short`).
