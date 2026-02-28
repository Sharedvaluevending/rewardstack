# AGENTS.md

## Cursor Cloud specific instructions

### Services overview

RewardStack is a Laravel 10 + Inertia.js (Vue 3) SaaS platform for QR code-based loyalty/rewards programs. See `README.md` and `docs/TECHNICAL_README.md` for full details.

### Required infrastructure

- **MySQL 8.0** on port 3307 and **Redis 7** on port 6380 via `docker compose -f docker-compose.dev-simple.yml up -d`
- Start Docker daemon first: `sudo dockerd &>/tmp/dockerd.log &` (wait ~3s for readiness)

### Running the app

- `php artisan serve --host=0.0.0.0 --port=8000` starts the dev server
- Frontend assets must be built first with `npm run build` (or use `npm run dev` for HMR during active frontend work)

### Gotchas

- The `EnsureActiveSubscription` middleware redirects business users to billing unless the business has `is_testing_account = true` or an active Stripe subscription. For local dev, set `is_testing_account = true` on the business record via tinker.
- `.env` must set `PRINTFUL_API_KEY` to a non-empty value (e.g. `test_key`) or `composer dump-autoload` / `php artisan package:discover` will crash with a TypeError in `PrintfulService`.
- PHPUnit tests use SQLite `:memory:` and array drivers (cache/queue/session/mail) per `phpunit.xml` — no external services needed for tests.
- For local dev without Redis, set `CACHE_DRIVER=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync` in `.env`.
- Docker containers expose MySQL on port **3307** and Redis on port **6380** (not the default ports).

### Common commands

| Task | Command |
|------|---------|
| PHP unit tests | `php artisan test --testsuite=Unit` |
| PHP feature tests | `php artisan test --testsuite=Feature` |
| JS tests | `npx vitest run` |
| Lint (PHP) | `vendor/bin/pint --test` |
| Fix lint | `vendor/bin/pint` |
| Build frontend | `npm run build` |
| Dev server | `php artisan serve --host=0.0.0.0 --port=8000` |
| Migrations | `php artisan migrate` |
| Seed DB | `php artisan db:seed` |

### Pre-existing issues

- 1 pre-existing unit test failure in `ReferralCommissionEarnedTest` (expects `['mail']` but notification now also returns `'database'` channel).
- 503 pre-existing Pint style violations across the codebase.
