# Reaching 80% Total App Coverage Safely

You're already running coverage (Docker for tests, coverage on areas like GameService, PrizeService, Stripe, etc.). This doc keeps the path to **80% total app coverage** safe so we don't break the platform.

## Current setup

- **Docker**: `scripts/docker-test.sh` runs the full suite in Docker (MySQL, isolated). Use this as the source of truth for “all tests pass.”
- **Coverage**: Run on the host (PCOV or Xdebug required). PHPUnit config is in `phpunit.xml` and `phpunit.docker.xml` with coverage reports:
  - **Text**: printed to stdout (summary + optional uncovered files).
  - **HTML**: `build/coverage/index.html` for per-file/per-line breakdown.

## Safe workflow (don’t break the platform)

1. **Before adding or changing any test**
   - Run the full suite (Docker or host) and ensure everything is green:
     - Docker: `./scripts/docker-test.sh`
     - Host: `php artisan test`

2. **Add tests in small batches**
   - Prefer 1–3 new tests (or one new test file) per change.
   - Re-run the **full** suite after each batch:
     - `./scripts/docker-test.sh` and/or `php artisan test`
   - Only then run coverage to see the new numbers.

3. **Run coverage**
   - On the host (needs PCOV or Xdebug):
     - `./scripts/coverage.sh`  
       (runs tests without coverage first, then with coverage)
     - Or: `php artisan test --coverage`
   - Open `build/coverage/index.html` to see which files/namespaces are below 80%.

4. **Prioritize low-coverage areas**
   - Use the HTML report to find:
     - Namespaces/directories with low line coverage.
     - Critical paths: `App\Services`, `App\Http\Controllers`, payment/webhook code.
   - Add tests that:
     - Use existing factories and HTTP/feature patterns.
     - Don’t change production code unless necessary (refactors in separate PRs).

5. **Never**
   - Skip the full test run after adding tests.
   - Add tests that depend on real external APIs or production DB.
   - Disable or alter existing tests to “fix” coverage.

## Quick commands

```bash
# Full suite (Docker) – source of truth
./scripts/docker-test.sh

# Full suite (host)
php artisan test

# Coverage (host only; runs tests then coverage)
./scripts/coverage.sh

# Or manually
php artisan test --coverage
# Then open: build/coverage/index.html
```

## Target

- **80% total app coverage** (for `app/`), with higher coverage (e.g. 90%+) on critical areas (payments, game/reward logic, webhooks) where you’re already strong.
- Keep the suite green in Docker and on the host after every change.
