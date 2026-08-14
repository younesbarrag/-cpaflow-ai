# CPAFlow AI

A CPA/affiliate campaign management platform built with Laravel, featuring AI-powered offer analysis and marketing content generation.

## Project Overview

CPAFlow AI is a SaaS application that enables affiliate marketers to manage the full lifecycle of CPA (Cost Per Action) campaigns from a single interface. It handles offer management, campaign tracking, conversion postback processing, expense tracking, financial metrics, and AI-assisted optimization.

**Key capabilities:**

- Offer CRUD with archive/restore lifecycle
- Campaign management with draft/active/suspended states
- Tracking link generation with click recording and UTM metadata
- Server-to-server conversion postback with HMAC-secured tokens
- Admin-only conversion review (approve/reject)
- Revenue, expenses, and profit calculation
- AI offer analysis (score, strengths, weaknesses, recommendations)
- AI marketing content generation (hooks, captions)
- Dashboard with configurable time-period statistics

## Problem Solved

Affiliate marketers typically juggle multiple disconnected tools: spreadsheets for tracking spend, affiliate network dashboards for conversions, manual calculations for profit, and separate AI tools for optimization. CPAFlow AI consolidates these into a single platform where marketers can manage offers, launch campaigns, track performance in real time, and leverage AI insights — all with a clear separation between affiliate actions and administrative oversight.

## User Roles

### Affiliate

- Manages own Offers (create, edit, archive, restore)
- Manages own Campaigns (create, edit, activate, suspend)
- Generates Tracking Links for campaigns
- Records ad spend as Campaign Expenses
- Views own Conversions and their statuses
- Uses AI analysis and content generation on own offers

### Admin

- Reviews Pending conversions across all affiliates
- Approves or Rejects conversions (determines revenue recognition)
- Cannot be the last admin demoted (protected by system constraint)
- Separation of duties: affiliates cannot approve their own earnings

## Business Flow

```
Offer → Campaign → Tracking Link → Click → Affiliate Network Postback
→ Pending Conversion → Admin Approve/Reject → Revenue → Expenses → Profit
```

1. **Offer**: User creates an offer with name, destination URL, payout, and description
2. **Campaign**: User creates a campaign linked to the offer, specifying traffic source and budget
3. **Tracking Link**: System generates a unique code (e.g., `/t/{code}`) for the campaign
4. **Click**: Visitor hits the tracking link; system records the click (IP hash, user agent, UTM params) and redirects to the offer's destination URL
5. **Postback**: Affiliate network sends a server-to-server GET request with external ID and HMAC token
6. **Pending Conversion**: System creates a conversion in `pending` status, revenue snapshotted from the offer payout
7. **Admin Review**: Admin reviews pending conversions, approves or rejects them
8. **Revenue**: Only approved conversions count toward revenue
9. **Expenses**: User manually records ad spend per campaign
10. **Profit**: Revenue minus Expenses

## Core Entities

| Entity | Purpose | Key Relationships |
|--------|---------|-------------------|
| **User** | Application user (Affiliate or Admin) | has many Offers |
| **Offer** | A CPA offer with destination URL and payout | belongs to User; has many Campaigns, one AiAnalysis, many AiGenerations |
| **Campaign** | An advertising campaign for an offer | belongs to Offer; has many TrackingLinks, Conversions, Expenses |
| **TrackingLink** | Unique generated URL code for a campaign | belongs to Campaign; has many TrackingClicks |
| **TrackingClick** | Recorded visitor click on a tracking link | belongs to TrackingLink; stores IP hash, user agent, UTM params |
| **Conversion** | A conversion event from an affiliate network postback | belongs to Campaign; stores external_id (unique), revenue, status |
| **CampaignExpense** | Manual ad spend entry for a campaign | belongs to Campaign; stores amount, date, description |
| **AiAnalysis** | AI-generated analysis of an offer | belongs to Offer; stores score, summary, strengths, weaknesses, recommendations |
| **AiGeneration** | AI-generated marketing content for an offer | belongs to Offer; stores hooks, captions |

### Enums

| Enum | Values |
|------|--------|
| `UserRole` | `affiliate`, `admin` |
| `OfferStatus` | `draft`, `active`, `suspended`, `archived` |
| `CampaignStatus` | `draft`, `active`, `suspended` |
| `ConversionStatus` | `pending`, `approved`, `rejected` |
| `AiProcessStatus` | `pending`, `processing`, `completed`, `failed` |

## Architecture

The project follows a layered Laravel architecture with clear separation of concerns:

| Layer | Responsibility | Examples |
|-------|---------------|----------|
| **Routes** | HTTP endpoint mapping | `routes/web.php`, `routes/api.php` |
| **Controllers** | HTTP coordination only | `CampaignController`, `OfferController` |
| **Form Requests** | Validation and authorization input | `UpdateCampaignRequest`, `StorePostbackConversionRequest` |
| **Actions** | Business use cases (one class per use case) | `CreateCampaignAction`, `ActivateCampaignAction`, `RecordConversionAction` |
| **Policies** | Ownership and role-based authorization | `CampaignPolicy`, `OfferPolicy`, `UserPolicy` |
| **Models / Eloquent** | Database relationships and scopes | `Campaign`, `Offer`, `Conversion` |
| **Enums** | Typed string constants | `CampaignStatus`, `ConversionStatus` |
| **Services** | Reusable technical logic | `PostbackSigner`, `AiOfferAnalyzer`, `AiContentGenerator` |
| **Jobs** | Async queue processing | `AnalyzeOfferJob`, `GenerateContentJob` |
| **DTOs** | Immutable data snapshots | `OfferAiInputSnapshot`, `DashboardStatisticsPeriod` |
| **Blade + Alpine.js** | Server-rendered UI with reactive components | Views in `resources/views/` |
| **Sanctum** | First-party API authentication from Blade | Bearer token auth for `/api/v1/*` |

## Authentication and Authorization

- **Web (Blade)**: Laravel session authentication via Breeze
- **API**: Sanctum stateful tokens for first-party Blade-to-API requests
- **Policies**: Ownership-based rules — users can only manage their own offers and campaigns
- **Admin middleware**: `EnsureUserIsAdmin` guards admin-only routes (conversion review, user management)
- **Conversion review**: Only Admin users can approve/reject conversions — affiliates cannot approve their own earnings

## Offers

Each offer represents a CPA deal with:

- **name**: Offer title
- **destination_url**: Where visitors are redirected after clicking
- **payout**: Revenue per conversion (decimal, 12 digits, 2 decimals)
- **status**: `draft` → `active` → `suspended` → `archived`
- **description**: Optional text description

Features: archive/restore, search by name, filter by status, AI analysis and content generation.

## Campaigns

Each campaign belongs to one offer and represents an advertising effort:

- **name**: Campaign title
- **traffic_source**: Where traffic comes from (e.g., Google Ads, Facebook)
- **budget**: Allocated spend limit
- **status**: `draft` → `active` → `suspended` (can reactivate from suspended)

**Immutable offer**: Once a campaign is created, its `offer_id` cannot be changed. The edit form does not submit `offer_id`; validation marks it as `prohibited` on update.

## Tracking

- **Tracking link generation**: Unique 32-character random code per campaign
- **Public redirect**: `GET /t/{code}` — records click (IP hash via HMAC-SHA256, user agent, referer, UTM parameters) and redirects (302) to the offer's destination URL
- **Click ≠ Conversion**: A click records a visitor visit. A conversion is a separate event received via postback from the affiliate network.

## Conversion Postback

Public server-to-server endpoint for affiliate networks:

```
GET /postback/{code}?external_id={id}&source={source}&token={token}
```

- **code**: Resolves the tracking link → campaign → offer
- **external_id**: Unique transaction/conversion identifier from the affiliate network
- **token**: HMAC-SHA256 signature of the code, verified with `hash_equals()` (timing-safe)
- **APP_KEY** is never exposed — used server-side only for HMAC computation
- **Invalid token**: 403 Forbidden
- **Missing/invalid input**: 422 Validation Error
- **Duplicate external_id**: Idempotent — first request returns `{"status":"ok","duplicate":false}`, retry returns `{"status":"ok","duplicate":true}`, database stores one conversion
- **GET method**: Used for compatibility with typical affiliate network postback implementations
- **Throttling**: `throttle:postback` middleware applied

## Conversion Review

- Postback creates conversions as `Pending`
- Affiliates cannot approve/reject their own conversions (policy enforcement)
- Admin reviews pending conversions via the Admin Conversion Review UI
- **Approved** conversions count toward revenue; **Rejected** do not
- Invalid state transitions are rejected with 409 Conflict

## Expenses

- Manual entry of ad spend per campaign
- Fields: amount, spent_at (date), description
- Full CRUD (create, read, update, delete)
- Separate from conversion revenue — not derived from network data

## Accounting / Metrics

```
Revenue  = SUM(conversions.revenue) WHERE status = 'approved'
Expenses = SUM(campaign_expenses.amount)
Profit   = Revenue - Expenses
```

- Pending and Rejected conversions do not contribute to revenue
- Conversion revenue is snapshotted from the Offer payout at conversion creation time
- Dashboard supports period filtering: today, last 7 days, last 30 days, this month, custom range

## AI Features

### Architecture

```
Blade UI → Authenticated API → Sanctum → Action → Queue Job → Prism → AI Provider → Database → Polling → UI
```

### Offer Analysis

- **Input**: Offer name, description, payout, destination URL
- **Output**: Score (0-100), summary, strengths[], weaknesses[], recommendations[]
- **Processing**: Asynchronous via `AnalyzeOfferJob` on the `default` queue
- **Staleness detection**: SHA-256 input hash tracks whether the offer has changed since analysis

### Content Generation

- **Input**: Offer data + completed analysis
- **Output**: hooks[] (ad headlines), captions[] (social media texts)
- **Prerequisite**: A completed, up-to-date analysis is required
- **Processing**: Asynchronous via `GenerateContentJob` on the `default` queue
- **Deduplication**: Unique job per offer prevents concurrent generation

### AI Provider

- **Integration**: [Prism](https://github.com/prism-php/prism) — Laravel AI abstraction layer
- **Provider/Model**: Configured via `AI_PROVIDER` and `AI_MODEL` environment variables
- **Current setup**: Groq provider for local evaluation
- **Testing**: Tests use Prism fakes — no real API calls during test runs

## Queue / Background Processing

AI operations are processed asynchronously via Laravel queues:

```bash
php artisan queue:work --queue=default
```

- `AnalyzeOfferJob`: 3 retries, 60s timeout, exponential backoff (30s, 60s)
- `GenerateContentJob`: 3 retries, 60s timeout, exponential backoff (30s, 60s)
- Rate-limited and provider-overloaded exceptions trigger automatic retry
- Permanent failures are logged and marked as `failed` in the database
- **202 Accepted**: API returns immediately; AI work is dispatched to the queue

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Language | PHP 8.3+ |
| Framework | Laravel 13 |
| Auth (web) | Laravel Breeze (Blade, session) |
| Auth (API) | Laravel Sanctum (Bearer token) |
| Frontend | Tailwind CSS + Alpine.js |
| Build | Vite |
| Tests | Pest |
| AI Integration | Prism |
| Style | Laravel Pint |
| CI | GitHub Actions |
| Database | MySQL (production/CI), SQLite (local tests) |

## Installation

See [docs/SETUP.md](docs/SETUP.md) for the complete setup guide.

Quick start:

```bash
git clone <repository>
cd cpaflow/BACKEND
composer install
cp .env.example .env
php artisan key:generate
# Configure DB_CONNECTION, DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env
php artisan migrate
npm install
npm run build
php artisan serve
# In another terminal:
php artisan queue:work --queue=default
```

## AI Configuration

Environment variables (no secrets in documentation):

```
AI_PROVIDER=groq
AI_MODEL=llama-3.3-70b-versatile
GROQ_API_KEY=your_key_here
```

Prism supports multiple providers. The evaluation setup uses Groq. OpenAI or Anthropic can be configured by changing `AI_PROVIDER`, `AI_MODEL`, and the corresponding API key variable.

## Demo Data

### Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@example.test | password |
| Affiliate | affiliate@example.test | password |
| Affiliate 2 | affiliate2@example.test | password |

**FOR LOCAL DEMO / DEVELOPMENT ONLY.**

### Seed Demo Data

```bash
php artisan db:seed --class=DemoDataSeeder
```

This creates demo offers, campaigns, tracking links, clicks, conversions (approved + pending), expenses, and pre-completed AI analysis/generation results.

## Testing

```bash
# Run full test suite
php artisan test

# Run specific test groups
php artisan test --filter=Campaign
php artisan test --filter=Offer

# Style check
vendor/bin/pint --test

# Frontend build
npm run build
```

Current status: **718 tests passing** (Pest, SQLite in-memory).

## Security Decisions

- **Ownership Policies**: Users can only access their own offers and campaigns
- **Admin-only conversion review**: Separation of duties — affiliates cannot approve their own earnings
- **Sanctum API protection**: First-party API requests require authentication
- **HMAC postback token**: `PostbackSigner` derives tokens using `APP_KEY` — key is never exposed
- **Timing-safe verification**: `hash_equals()` prevents timing attacks on token comparison
- **Prohibited fields**: `revenue`, `status`, `campaign_id`, `converted_at` are prohibited on postback — prevents clients from forging conversions
- **Database uniqueness**: `external_id` has a unique constraint — duplicate postbacks are idempotent
- **No APP_KEY exposure**: Key is used server-side only; never sent to clients

## Known Limitations

- **No click-level attribution**: Conversions are linked to campaigns via tracking links but are not tied to specific clicks
- **No automated ad-platform sync**: Expenses are entered manually; no direct Facebook/Google/TikTok Ads API integration
- **AI depends on queue worker**: If the queue worker is not running, AI jobs remain in `pending` status

## License

Proprietary — CPAFlow AI
