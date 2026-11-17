# Pixelforge Studio

PromptEdit Senior Product Engineer take-home: Laravel + Vue + Inertia app that generates images through the [Pixelforge API](https://pixelforge.promptedit.dev/docs), shows a live price estimate, charges Pixelforge cost + 25% markup exactly once, and handles duplicate / out-of-order webhooks.

## Stack

Laravel 13 · Vue 3 · Inertia 3 · Tailwind 4 · PostgreSQL · database queue

## Features

- **Generate** — prompt, size, quality, optional reference URLs, live estimate (expected + range)
- **Pricing** — nanodollar integer maths; user price = provider × 1.25 rounded up; failed gens are $0
- **History** — live status polling while in flight; estimated vs actual cost; image when ready
- **Webhooks** — HMAC verification, event-id dedupe, forward-only status sync
- **Balances** — hold max estimate on submit, fake “Buy $10 credits”, block if unaffordable
- **Admin report** — spend / provider / profit by UTC day and user, reconciled to `GET /v1/usage`

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* (pgsql), PIXELFORGE_API_KEY, PIXELFORGE_WEBHOOK_SECRET
createdb pixelforge
php artisan migrate
php artisan db:seed
npm install && npm run build
composer run dev
```

In another terminal:

```bash
php artisan queue:work --queue=pixelforge,default
php artisan schedule:work
```

Point your Pixelforge key’s webhook URL at `https://<public-host>/webhooks/pixelforge` (use ngrok locally).

### Demo login

| Email | Password |
|---|---|
| `demo@pixelforge.test` | `DEMO_PASSWORD` env (default `password`) |

Admin report: `/admin/report`

## Tests

```bash
php artisan test
```

## Deploy notes

- Use a **separate** Pixelforge API key for production; set its webhook URL and `PIXELFORGE_WEBHOOK_SECRET`
- Run a queue worker and scheduler
- Leave a few `[succeed]` / `[fail]` / `[duplicate]` generations for reviewers
