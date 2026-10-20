# Roanoke Local Business Growth Initiative — Membership Platform

A membership-style web application for the **Roanoke Local Business Growth Initiative**. Local businesses pay a monthly membership fee to access marketing reports, social media promotion schedules, educational resources, event calendars, and premium marketing upsells — all through a secure, self-service portal. Program administrators manage memberships, upload reports, coordinate promotions, fulfill orders, and track program-wide impact through a dedicated admin dashboard.

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Environment Configuration](#environment-configuration)
- [Running Locally](#running-locally)
- [Building for Production](#building-for-production)
- [Testing](#testing)
- [Roles & Permissions](#roles--permissions)
- [Database & Migrations](#database--migrations)
- [Key Integrations](#key-integrations)
- [Scripts Reference](#scripts-reference)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

The platform serves three audiences:

| Audience | What they do |
|---|---|
| **Public visitors** | Learn about the initiative, view pricing, sign up |
| **Member businesses** | Access reports, schedules, resources, upsells, billing — their own data only |
| **Super Admins** (platform owners) | Manage all businesses, upload reports, run events, fulfill orders, track impact |

The frontend is split: **public pages are Blade templates**, while **portals (member + admin) are React dashboards** mounted into Blade via Vite. There is no separate SPA or Next.js app.

---

## Tech Stack

### Backend
- **PHP** `^8.2`
- **Laravel** `^12.0`
- **Laravel Fortify** `^1.37` — authentication (Magic Link / email-based login)
- **Laravel Sanctum** `^4.3` — API token auth for the React dashboards
- **Spatie Laravel Permission** `^6.25` — role & permission management (`super_admin`, etc.)
- **Spatie Laravel Activity Log** `^4.12` — audit trail for member activity tracking
- **Barryvdh Laravel DomPDF** `^3.1` — PDF generation for reports
- **Laravel Tinker** `^2.10` — REPL for debugging

### Frontend
- **React** `^19.3.1` + **React DOM** `^19.3.1` — portal dashboards
- **Tailwind CSS** `^4.0.0` — styling (via `@tailwindcss/vite`)
- **Vite** `^7.0.7` — build tool
- **Laravel Vite Plugin** `^2.0.0` — Blade ↔ Vite integration
- **Axios** `^1.11.0` — HTTP client for React → Laravel API calls

### Dev Tooling
- **PHPUnit** `^11.5.50` — backend testing
- **Laravel Pint** `^1.24` — PHP code style
- **Laravel Pail** `^1.2.2` — real-time log viewer
- **Laravel Sail** `^1.41` — Docker dev environment (optional)
- **Laravel Collision** `^8.6` — pretty error reporting
- **Concurrently** `^9.0.1` — run multiple dev processes at once
- **Faker** `^1.23` — test/seed data
- **Mockery** `^1.6` — mocking for tests

### Infrastructure
- **Database:** MySQL / PostgreSQL / SQLite (configurable via `.env`)
- **Cache & Queue:** Database driver by default (configurable)
- **File Storage:** Local disk by default (reports, resources, logos)

---

## Features

### Public Website (Blade)
- Landing page with initiative overview, benefits, and how-it-works
- Pricing page ($200/month membership)
- Sign-up flow: business info → Magic Link auth → Stripe Checkout
- Login via Magic Link (email)

### Member Portal (React)
- **Dashboard** — welcome message, quick stats (next social highlight, last month's reach, upcoming events), shortcuts
- **Reports** — view and download monthly marketing reports (PDFs), archive of past months
- **Schedule** — social media highlight calendar + community event calendar, RSVP
- **Resources** — how-to guides, branding guidelines, marketing tips, educational event schedule
- **Upsells** — browse and purchase premium marketing services (Google Ads, video promos, boosts)
- **Billing** — view billing history, update payment method, cancel membership (via Stripe Customer Portal)
- **Profile** — update business info, logo, contact details, social links

### Super Admin Portal (React)
- **Dashboard** — key metrics (active businesses, MRR, upsells sold, engagement), charts, recent activity
- **Businesses** — view/edit all member businesses, upload reports, view orders, manage status
- **Reports** — upload monthly reports per business (single or bulk), draft → publish, version history
- **Events** — create/edit/delete events, view RSVPs, mark attendance
- **Upsells** — manage product catalog (create, edit, deactivate)
- **Orders** — view all upsell purchases, mark fulfilled, filter by status
- **Metrics** — manual entry for sales tax, marketing reach, engagement; export CSV
- **Settings** — admin user management, branding guidelines, resource library

### Activity & Engagement Tracking
- Automatic member activity logging (logins, report views/downloads, resource views/downloads)
- Event registration and attendance tracking
- Member dashboard shows recent activity and engagement summary (last 30 days)
- Super Admin dashboard shows global activity feed and per-business engagement summaries
- Cross-business data isolation enforced at the query and authorization layer

---

## Requirements

- **PHP** 8.2 or higher
- **Composer** 2.x
- **Node.js** 20.x or higher
- **npm** 10.x or higher
- **Database** — MySQL 8+, PostgreSQL 14+, or SQLite 3
- **Stripe account** — for subscriptions and one-time payments (test mode is fine for dev)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/zZJoennZz/client-dashboard/
cd client-dashboard
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node dependencies

```bash
npm install
```

### 4. Set up environment

```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env` with your database, Stripe, and mail credentials (see [Environment Configuration](#environment-configuration)).

### 5. Run migrations and seeders

```bash
php artisan migrate
php artisan db:seed
```

> The seeder creates a Super Admin account. Check `database/seeders/` for the default credentials (change them immediately in non-local environments).

### 6. Build frontend assets

```bash
npm run build
```

### 7. Start the dev server

```bash
composer run dev
```

This runs **four processes concurrently**:
- `php artisan serve` — Laravel dev server (http://localhost:8000)
- `php artisan queue:listen` — queue worker
- `php artisan pail` — real-time log viewer
- `npm run dev` — Vite dev server (hot reload for React + Tailwind)

Open **http://localhost:8000** in your browser.

---

## Environment Configuration

Key variables in `.env`:

```env
# Application
APP_NAME="Roanoke Local Business Growth Initiative"
APP_ENV=local
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=roanoke_growth
DB_USERNAME=root
DB_PASSWORD=

# Authentication (Fortify Magic Link)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="hello@roanokegrowth.test"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum (React → API auth)
SANCTUM_STATEFUL_DOMAINS=localhost:8000

# Stripe (subscriptions + upsells)
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Membership
MEMBERSHIP_PRICE_MONTHLY=200
```

> **Never commit `.env`.** Use `.env.example` as the template. See `.env.example` for the full list.

---

## Running Locally

### Standard (without Docker)

```bash
composer run dev
```

This is the recommended way. It starts the Laravel server, queue worker, log viewer, and Vite dev server in one terminal with color-coded output.

### Useful URLs

| URL | What |
|---|---|
| `http://localhost:8000` | Public site |
| `http://localhost:8000/login` | Member login (Magic Link) |
| `http://localhost:8000/signup` | Member sign-up |
| `http://localhost:8000/dashboard` | Member portal (after login) |
| `http://localhost:8000/admin/dashboard` | Super Admin portal (after login as admin) |
| `http://localhost:8000/telescope` | (If installed) Debugging |

---

## Building for Production

```bash
# 1. Install production dependencies
composer install --no-dev --optimize-autoloader
npm ci

# 2. Build frontend
npm run build

# 3. Cache Laravel config, routes, views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Run migrations
php artisan migrate --force
```

> The `composer run setup` script automates steps 1–4 for a fresh environment (see `composer.json`).

---

## Testing

### Run the full test suite

```bash
composer run test
```

This runs `php artisan config:clear` followed by `php artisan test`.

### Run a specific test file

```bash
php artisan test tests/Feature/ActivityTrackingTest.php
```

### Run with coverage

```bash
php artisan test --coverage
```

### Code style (Pint)

```bash
./vendor/bin/pint
```

### What's tested

- Authentication (Magic Link flow, session handling)
- Activity tracking (logins, report views, deduplication)
- Event registration and attendance
- Cross-business data isolation (member A cannot see member B's data)
- Super Admin access and permissions
- Dashboard rendering and empty states
- Existing registration and event workflows

---

## Roles & Permissions

The platform uses **Spatie Laravel Permission** with two roles:

### `super_admin`
The platform owner/operator (the Roanoke initiative team).

**Can:**
- Read/write all businesses, reports, events, upsells, orders, metrics
- Manage admin users
- Access all `/admin/*` routes
- View global activity and engagement summaries

**Cannot:**
- View or store raw card data
- Impersonate members
- Permanently delete business history (use archive/cancel)
- Modify subscription prices outside Stripe

### `member` (business user)
A business owner or staff member with access to their own business's portal.

**Can:**
- View/download their own reports
- View their own schedule and events
- RSVP to events
- Purchase upsells
- Manage their own profile and billing
- View their own activity summary

**Cannot:**
- Access another business's data
- Access `/admin/*` routes
- Modify platform settings

> Role management is in `database/seeders/RoleSeeder.php`. The `admins` table has a `role` column reserved for future admin tiers (e.g. `event_manager`, `billing_admin`).

---

## Database & Migrations

### Key tables

| Table | Purpose |
|---|---|
| `users` | All authenticated users (members + admins) |
| `businesses` | Member business profiles |
| `subscriptions` | Stripe subscription records |
| `reports` | Monthly marketing reports (per business) |
| `report_metrics` | Structured metrics (reach, clicks, etc.) — Phase 2 |
| `events` | Community events and workshops |
| `event_registrations` | RSVPs and attendance |
| `upsells` | Premium service catalog |
| `orders` | Upsell purchases |
| `member_activity` | Activity log (logins, views, downloads) |
| `metrics` | Manually entered program metrics (sales tax, etc.) |

### Migration rules

- **Never** use `migrate:fresh`, `db:wipe`, or destructive commands in shared environments.
- New migrations must have a working `down()` method.
- Use `Schema::hasTable` / `Schema::hasColumn` guards for idempotency.
- Do not modify existing migrations — add new ones.

### Seeding

```bash
# All seeders
php artisan db:seed

# Specific seeder
php artisan db:seed --class=RoleSeeder
```

---

## Key Integrations

### Stripe
- **Subscriptions** — $200/month recurring membership (Stripe Checkout Sessions)
- **One-time payments** — upsell purchases (Google Ads, video promos, boosts)
- **Customer Portal** — members update payment methods and cancel via Stripe's hosted portal
- **Webhooks** — update `subscriptions` and `orders` on payment success/failure

**Setup:**
1. Add `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` to `.env`
2. Configure webhook endpoint in Stripe Dashboard: `https://your-domain.com/api/stripe/webhook`
3. Use `stripe listen --forward-to localhost:8000/api/stripe/webhook` for local dev

### Magic Link Authentication (Fortify)
- Passwordless login via email link
- No password storage or reset flows
- Magic link expiry: 15 minutes (configurable in `config/fortify.php`)

### PDF Generation (DomPDF)
- Monthly reports can be generated server-side and stored in `storage/app/reports/`
- Reports are served via signed URLs (short-lived) after authorization check

### Activity Log (Spatie)
- Member activity tracked in `member_activity` table
- Deduplication: one `login` per session, one `report_viewed` per user/subject/day
- No sensitive data in `metadata` (no passwords, tokens, session IDs, request bodies)

---

## Scripts Reference

### Composer scripts

| Script | What it does |
|---|---|
| `composer run setup` | Full fresh setup: install deps, copy `.env`, generate key, migrate, build frontend |
| `composer run dev` | Start dev environment (server + queue + logs + Vite) |
| `composer run test` | Clear config cache and run PHPUnit test suite |

### npm scripts

| Script | What it does |
|---|---|
| `npm run dev` | Start Vite dev server (hot reload) |
| `npm run build` | Build production assets to `public/build/` |

### Artisan commands (common)

```bash
php artisan migrate              # Run pending migrations
php artisan migrate:status       # Show migration status
php artisan db:seed              # Run seeders
php artisan test                 # Run tests
php artisan route:list           # List all routes
php artisan pail                 # Real-time log viewer
php artisan queue:listen         # Process queued jobs
```

---

## Contributing

### Workflow

1. Create a feature branch: `git checkout -b feature/your-feature`
2. Make changes following existing code style (run `./vendor/bin/pint` for PHP)
3. Add tests for new behavior
4. Run `composer run test` — all tests must pass
5. Run `npm run build` — frontend must compile cleanly
6. Open a pull request with a clear description and test evidence

### Code style

- **PHP:** Laravel Pint (`./vendor/bin/pint`)
- **JavaScript/React:** Follow existing patterns; no linter configured yet — match surrounding code
- **Tailwind:** Use utility classes; avoid arbitrary values unless necessary

### Scope discipline

- Public pages remain **Blade templates** — do not introduce Next.js, Vue, Inertia, or a separate frontend app
- Do not add new dependencies without justification
- Do not use destructive migrations
- Do not implement payment logic outside Stripe integration
- Cross-business data isolation is non-negotiable — every member query must filter by `business_id`

---

## License

This project is proprietary software for the **Roanoke Local Business Growth Initiative**.

All rights reserved. Unauthorized copying, modification, or distribution is prohibited.

---

## Questions?

Contact the project maintainer or open an issue in the repository.