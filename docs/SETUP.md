# Setup Guide

Local development setup for CPAFlow AI.

## Prerequisites

- PHP 8.3+
- Composer
- Node.js 18+ (with npm)
- MySQL 8.4+ (production/CI) or SQLite (local development)
- A running queue worker (for AI features)

## Installation

```bash
# 1. Clone the repository
git clone <repository-url>
cd cpaflow/BACKEND

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies
npm install

# 4. Create environment file
cp .env.example .env

# 5. Generate application key
php artisan key:generate

# 6. Configure database in .env
# For MySQL:
#   DB_CONNECTION=mysql
#   DB_HOST=127.0.0.1
#   DB_PORT=3306
#   DB_DATABASE=cpaflow_ai
#   DB_USERNAME=root
#   DB_PASSWORD=
#
# For SQLite:
#   DB_CONNECTION=sqlite
#   DB_DATABASE=/absolute/path/to/database.sqlite

# 7. Run migrations
php artisan migrate

# 8. Build frontend assets
npm run build

# 9. Start the development server
php artisan serve
```

## Environment Configuration

### Database

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaflow_ai
DB_USERNAME=root
DB_PASSWORD=
```

### Queue

```
QUEUE_CONNECTION=database
```

The default queue connection uses the database. AI jobs are dispatched to the `default` queue.

### AI (Optional — needed for AI features)

```
AI_PROVIDER=groq
AI_MODEL=llama-3.3-70b-versatile
GROQ_API_KEY=your_api_key_here
```

AI features work without configuration in development — tests use fakes and don't call the real provider. For live AI, set the provider and API key.

### Session

```
SESSION_DRIVER=database
```

## Seed Demo Data

```bash
php artisan db:seed --class=DemoDataSeeder
```

This creates:

| Account | Role | Email | Password |
|---------|------|-------|----------|
| Admin | admin | admin@example.test | password |
| Affiliate | affiliate | affiliate@example.test | password |
| Affiliate 2 | affiliate | affiliate2@example.test | password |

Plus demo offers, campaigns, tracking links, clicks, conversions, expenses, and pre-completed AI results.

**FOR LOCAL DEMO / DEVELOPMENT ONLY.**

## Run the Application

```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Queue worker (required for AI features)
php artisan queue:work --queue=default
```

The queue worker must be running for AI analysis and content generation to process.

## Run Tests

```bash
# Full test suite
php artisan test

# Specific filter
php artisan test --filter=Campaign

# Style check
vendor/bin/pint --test

# Frontend build
npm run build
```

## Troubleshooting

### AI request stays Pending

The queue worker is not running. Start it:

```bash
php artisan queue:work --queue=default
```

Check if jobs are queued:

```bash
php artisan queue:table
php artisan migrate  # if jobs table doesn't exist
```

### AI provider 429 / rate limited

The AI provider (Groq, OpenAI, etc.) is rate-limiting requests. Check your provider dashboard for quota/billing status. The jobs automatically retry with exponential backoff (30s, 60s).

### API returns 401 unauthenticated from Blade

The Sanctum stateful middleware handles first-party authentication from the Blade frontend. Ensure:

- `SESSION_DRIVER=database` in `.env`
- The `personal_access_tokens` table exists (run `php artisan migrate`)
- The user is logged in via the web auth system

### Postback returns duplicate: true

This is expected idempotent behavior. The same `external_id` was already recorded. The first request created the conversion; subsequent requests return `{"status":"ok","duplicate":true}` without creating duplicates.

### Campaign update: "offer_id prohibited"

This is correct behavior. The edit form should not submit `offer_id`. The campaign's offer is immutable after creation. If you see this error, the form is incorrectly including `offer_id` in the request — check the Blade template at `resources/views/campaigns/partials/form.blade.php` to ensure the hidden input is not present in edit mode.

### Migration fails on existing table

Clear and re-run:

```bash
php artisan migrate:fresh --seed
```

**Warning**: This destroys all data. Use only in development.

### Vite build fails

Clear node_modules and rebuild:

```bash
rm -rf node_modules
npm install
npm run build
```
