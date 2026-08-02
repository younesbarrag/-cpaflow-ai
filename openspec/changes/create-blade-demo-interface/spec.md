# Specification - KAN-31: Create Blade Demo Interface

## 1. Functional Requirements

### R1 - Application Shell

| ID | Requirement |
|----|-------------|
| R1.1 | Authenticated users see a professional application shell with top bar, navigation, and content area. |
| R1.2 | The shell includes a logo/brand name linking to `/dashboard`. |
| R1.3 | Navigation links are present: Overview, Offers, Campaigns. |
| R1.4 | A user dropdown menu shows the authenticated user's name with links to Profile and Logout. |
| R1.5 | The active navigation link is visually distinguished. |
| R1.6 | Flash messages are displayed after successful mutations and can be dismissed. |
| R1.7 | The shell is responsive: desktop, tablet, and mobile layouts. |

### R2 - Authentication

| ID | Requirement |
|----|-------------|
| R2.1 | Unauthenticated users accessing protected pages are redirected to `/login`. |
| R2.2 | The login screen renders with email and password fields, remember-me checkbox, and forgot-password link. |
| R2.3 | The register screen renders with name, email, password, and password-confirmation fields. |
| R2.4 | Login/register/forgot-password/reset-password/verify-email/confirm-password screens are accessible. |
| R2.5 | Auth screens use the guest layout with CPAFlow branding. |
| R2.6 | Validation errors display inline on auth forms. |
| R2.7 | Logout is performed via POST form with CSRF protection and redirects to `/`. |

### R3 - Profile

| ID | Requirement |
|----|-------------|
| R3.1 | The profile screen displays name, email, and account management sections. |
| R3.2 | Profile information can be updated with validation feedback. |
| R3.3 | Password can be updated with current-password verification. |
| R3.4 | Account deletion requires password confirmation. |
| R3.5 | Successful profile update shows a success flash message. |

### R4 - Offer Index

| ID | Requirement |
|----|-------------|
| R4.1 | The offer index page displays a paginated list of the authenticated user's offers. |
| R4.2 | Each offer row shows: name, destination URL, payout, status badge, and actions. |
| R4.3 | Search by name is available. |
| R4.4 | Filter by status (draft/active/suspended/archived) is available. |
| R4.5 | An empty state is displayed when no offers exist. |
| R4.6 | Only the authenticated user's own offers are displayed. |
| R4.7 | A "Create Offer" button links to the create form. |

### R5 - Offer Create

| ID | Requirement |
|----|-------------|
| R5.1 | The create form renders with fields: name, destination_url, payout, status, description. |
| R5.2 | Validation rules match the backend Offer validation. |
| R5.3 | Validation errors render inline next to each field. |
| R5.4 | On success, the user is redirected to the offer index with a success flash. |
| R5.5 | Form retains old input values on validation failure. |

### R6 - Offer Edit

| ID | Requirement |
|----|-------------|
| R6.1 | The edit form renders pre-filled with the offer's current values. |
| R6.2 | Authorization is enforced: only the offer owner can access the edit form. |
| R6.3 | On success, the user is redirected with a success flash. |
| R6.4 | A foreign offer returns 403 Forbidden. |

### R7 - Offer Archive

| ID | Requirement |
|----|-------------|
| R7.1 | Archive is performed via POST form with CSRF. |
| R7.2 | A confirmation prompt is shown before archiving. |
| R7.3 | Authorization is enforced server-side. |
| R7.4 | On success, the offer status becomes "archived" and a flash is shown. |

### R8 - Campaign Index

| ID | Requirement |
|----|-------------|
| R8.1 | The campaign index page displays a paginated list of the user's campaigns. |
| R8.2 | Each campaign row shows: name, offer name, traffic source, budget, status badge, and actions. |
| R8.3 | An empty state is displayed when no campaigns exist. |
| R8.4 | Only the authenticated user's own campaigns are displayed (via Offer ownership). |

### R9 - Campaign Create

| ID | Requirement |
|----|-------------|
| R9.1 | The create form renders with a dropdown of the user's non-archived offers. |
| R9.2 | Form fields: offer_id (dropdown), name, traffic_source, budget. |
| R9.3 | Archived offers do not appear in the dropdown. |
| R9.4 | Validation errors render inline. |
| R9.5 | On success, redirect with flash. |

### R10 - Campaign Edit

| ID | Requirement |
|----|-------------|
| R10.1 | The edit form renders pre-filled. |
| R10.2 | Authorization enforced: only owner can edit. |
| R10.3 | On success, redirect with flash. |

### R11 - Campaign Lifecycle

| ID | Requirement |
|----|-------------|
| R11.1 | Activate button is shown only when campaign status is draft or suspended. |
| R11.2 | Suspend button is shown only when campaign status is active. |
| R11.3 | Activation and suspension are performed via POST with CSRF. |
| R11.4 | A confirmation prompt is shown before lifecycle transitions. |
| R11.5 | Authorization is enforced server-side. |
| R11.6 | Domain errors (invalid transition) are displayed as error flash messages. |
| R11.7 | Foreign campaigns cannot be activated or suspended (403). |

### R12 - Tracking Links

| ID | Requirement |
|----|-------------|
| R12.1 | The campaign detail page shows a tracking links section. |
| R12.2 | A "Generate Tracking Link" button is available only for active campaigns. |
| R12.3 | Generating a link is performed via POST with CSRF. |
| R12.4 | The generated tracking URL is displayed in the UI. |
| R12.5 | A copy-to-clipboard button is provided for each tracking URL. |
| R12.6 | Multiple tracking links can be displayed if generation is repeated. |
| R12.7 | The tracking URL format is `{APP_URL}/t/{code}`. |

### R13 - Public Tracking Flow

| ID | Requirement |
|----|-------------|
| R13.1 | The evaluator can generate a tracking link from the campaign detail page. |
| R13.2 | The evaluator can copy the tracking URL. |
| R13.3 | Opening the tracking URL in a new tab results in a 302 redirect to the Offer destination URL. |

### R14 - UX States

| ID | Requirement |
|----|-------------|
| R14.1 | Empty states render for Offer and Campaign lists when no data exists. |
| R14.2 | Validation errors render inline on all forms. |
| R14.3 | Success flash messages render after all successful mutations. |
| R14.4 | Error flash messages render for domain errors and authorization failures. |
| R14.5 | Unauthorized/forbidden access returns appropriate HTTP status (403/404). |

### R15 - Responsive Design

| ID | Requirement |
|----|-------------|
| R15.1 | The interface is usable on desktop (≥1024px), tablet (≥768px), and mobile (<640px). |
| R15.2 | Navigation collapses to hamburger menu on mobile. |
| R15.3 | Tables transform to card layout on mobile. |
| R15.4 | Forms are single-column and full-width on all screen sizes. |

### R16 - Accessibility

| ID | Requirement |
|----|-------------|
| R16.1 | All form fields have associated labels. |
| R16.2 | Interactive elements have visible focus states. |
| R16.3 | Status badges include text labels (not color-only). |
| R16.4 | Semantic HTML elements are used (headings, nav, main, section). |
| R16.5 | Icon-only buttons have aria-label attributes. |

### R17 - JavaScript

| ID | Requirement |
|----|-------------|
| R17.1 | Mobile navigation toggle uses Alpine.js. |
| R17.2 | Copy-to-clipboard uses navigator.clipboard API. |
| R17.3 | Flash messages are dismissable via Alpine.js. |
| R17.4 | Destructive actions use window.confirm() for confirmation. |
| R17.5 | No new frontend frameworks are added. |

## 2. Scenarios

### S1 - Guest Access Redirect

**Given** a guest (not authenticated)
**When** they visit `GET /offers`
**Then** they are redirected to `/login` (302)

### S2 - Authenticated User Sees Shell

**Given** an authenticated user
**When** they visit `GET /dashboard`
**Then** the page contains navigation links (Offers, Campaigns) and user menu

### S3 - Offer Ownership Isolation

**Given** two users A and B, each with offers
**When** user A visits `GET /offers`
**Then** only user A's offers are displayed; user B's offers are absent

### S4 - Offer Create Success

**Given** an authenticated user
**When** they submit a valid offer form
**Then** the offer is created in the database, the user is redirected to `/offers`, and a success flash is shown

### S5 - Offer Validation Errors

**Given** an authenticated user
**When** they submit an offer form with missing required fields
**Then** validation errors are displayed inline and old input is retained

### S6 - Offer Edit Authorization

**Given** user A owns an offer
**When** user B visits `GET /offers/{A-offer}/edit`
**Then** a 403 Forbidden response is returned

### S7 - Campaign Create With Eligible Offers

**Given** an authenticated user with one active offer and one archived offer
**When** they visit `GET /campaigns/create`
**Then** the offer dropdown shows only the active offer

### S8 - Campaign Lifecycle Activation

**Given** a user with a draft campaign
**When** they submit the activate form
**Then** the campaign status becomes active and a success flash is shown

### S9 - Campaign Lifecycle Domain Error

**Given** a user with an active campaign
**When** they attempt to activate it again
**Then** an error flash is shown ("This campaign cannot be activated.")

### S10 - Tracking Link Generation

**Given** a user with an active campaign
**When** they click "Generate Tracking Link"
**Then** a tracking link is created, the URL is displayed, and a success flash is shown

### S11 - Tracking Link Inactive Campaign

**Given** a user with a draft campaign
**When** they attempt to generate a tracking link
**Then** an error is returned (403 or domain error)

### S12 - Public Tracking Redirect

**Given** a valid tracking code for an active campaign
**When** a visitor opens `GET /t/{code}`
**Then** they are redirected (302) to the Offer destination URL

### S13 - Profile Update

**Given** an authenticated user
**When** they update their profile name
**Then** the name is updated, the user is redirected to `/profile`, and a success flash is shown

### S14 - Flash Messages Render

**Given** an authenticated user who just created an offer
**When** the page loads after redirect
**Then** a success flash message is visible and can be dismissed

### S15 - Mobile Navigation

**Given** a user on a mobile device
**When** they tap the hamburger menu
**Then** the navigation panel opens with all nav links and user info

## 3. HTTP Behavior

### 3.1 Protected Routes

All routes under `/offers`, `/campaigns`, `/profile`, `/dashboard` require authentication.

| Request | Response |
|---------|----------|
| `GET /offers` (guest) | 302 → `/login` |
| `GET /campaigns` (guest) | 302 → `/login` |
| `GET /dashboard` (guest) | 302 → `/login` |

### 3.2 Offer Routes

| Method | URI | Auth | Success | Validation Error | Authorization Error |
|--------|-----|------|---------|-----------------|-------------------|
| GET | `/offers` | Yes | 200 | — | — |
| GET | `/offers/create` | Yes | 200 | — | — |
| POST | `/offers` | Yes | 302 → `/offers` | 302 → `/offers/create` (errors) | — |
| GET | `/offers/{offer}/edit` | Yes | 200 | — | 403 |
| PATCH | `/offers/{offer}` | Yes | 302 → `/offers` | 302 → back (errors) | 403 |
| POST | `/offers/{offer}/archive` | Yes | 302 → `/offers` | — | 403 |

### 3.3 Campaign Routes

| Method | URI | Auth | Success | Validation Error | Authorization Error |
|--------|-----|------|---------|-----------------|-------------------|
| GET | `/campaigns` | Yes | 200 | — | — |
| GET | `/campaigns/create` | Yes | 200 | — | — |
| POST | `/campaigns` | Yes | 302 → `/campaigns` | 302 → back (errors) | 403 |
| GET | `/campaigns/{campaign}` | Yes | 200 | — | 403 |
| GET | `/campaigns/{campaign}/edit` | Yes | 200 | — | 403 |
| PATCH | `/campaigns/{campaign}` | Yes | 302 → `/campaigns` | 302 → back (errors) | 403 |
| POST | `/campaigns/{campaign}/activate` | Yes | 302 → `/campaigns` | — | 403 |
| POST | `/campaigns/{campaign}/suspend` | Yes | 302 → `/campaigns` | — | 403 |
| POST | `/campaigns/{campaign}/tracking-links` | Yes | 302 → back | — | 403 |

### 3.4 Profile Routes (Existing)

| Method | URI | Success | Validation Error |
|--------|-----|---------|-----------------|
| GET | `/profile` | 200 | — |
| PATCH | `/profile` | 302 → `/profile` | 302 → back (errors) |
| DELETE | `/profile` | 302 → `/` | 302 → back (errors) |

## 4. Acceptance Criteria Mapping

| Criterion | Requirement |
|-----------|-------------|
| Authenticated user sees application shell | R1.1, R1.2, R1.3, R1.4 |
| User can log in, register, log out | R2.1–R2.7 |
| User can view and edit profile | R3.1–R3.5 |
| User can create, list, edit, archive Offers | R4.1–R7.4 |
| User can create Campaigns with eligible Offers | R8.1–R9.5 |
| User can activate and suspend Campaigns | R11.1–R11.7 |
| User can generate and copy TrackingLinks | R12.1–R12.7 |
| User can open public tracking link and observe redirect | R13.1–R13.3 |
| All mutations show flash messages | R14.3 |
| Validation errors render inline | R14.2 |
| Empty states display | R14.1 |
| Foreign resources return 403/404 | R14.5 |
| All Pest feature tests pass | R15, R16 (tested separately) |
| npm run build succeeds | Build verification |
| vendor/bin/pint --test passes | Code style verification |
