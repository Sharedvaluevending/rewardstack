# Plan to Reach 80% Total App Coverage

## What to run (quick reference)

| Goal | Command |
|------|---------|
| **Get real coverage %** | `cd /var/www/rewardstack` then `./scripts/coverage-full.sh` — let it finish (10–20+ min), then open `build/coverage/index.html` and check the **Total** row. |
| **Run all tests (no coverage)** | `php artisan test` |
| **Run tests in Docker** | `./scripts/docker-test.sh` |
| **Coverage in two steps** | `./scripts/coverage.sh` (runs full suite, then coverage) |

**Files that describe what we’ve done:**  
- **This file** (`docs/COVERAGE_PLAN_80.md`) – why 0.76% is misleading, how to get real %, path to 80%.  
- `docs/COVERAGE_80.md` – safe workflow (Docker, small batches, prioritize areas).  
- `docs/TESTING_SUMMARY.md` – test setup, quick start, critical tests, coverage goals.

---

## Why you keep seeing ~0.76%

**The coverage report is overwritten every time you run tests.**

- When you run **only some tests** with `--coverage` (e.g. one file or a filter), **only those tests run**. The report then shows coverage for just that slice of the app, so the total looks tiny (e.g. 0.76%, 166 / 21,770 lines).
- That does **not** mean the project only has 0.76% coverage. It means the **last run** that generated `build/coverage/` was a **partial** run.

**To see the real total % you must run the FULL test suite with coverage once.**

- Full suite = all Unit + all Feature tests.
- One full run can take 10–15+ minutes. Use the script below so it’s consistent.

---

## How to get the real coverage %

**Option A – Host (needs PCOV or Xdebug)**

```bash
./scripts/coverage-full.sh
```

Then open `build/coverage/index.html` and read the **Total** line. That is your real app coverage.

**Option B – Docker**

If the app image has coverage enabled (PCOV/Xdebug), run the full suite with coverage inside Docker so it doesn’t time out on the host:

```bash
# From project root; uses phpunit.docker.xml (MySQL, coverage to build/coverage)
docker compose -f docker-compose.testing.yml up -d db redis
docker compose -f docker-compose.testing.yml run --rm app ./vendor/bin/phpunit -c phpunit.docker.xml
docker compose -f docker-compose.testing.yml down -v
```

Then open `build/coverage/index.html` for the real %.

---

## Path to 80%

- **App size:** ~21,770 lines. 80% ≈ 17,416 lines covered.
- After a **full** run, you’ll likely see something in the **mid‑50%** range (you’ve added many tests). The gap to 80% is roughly **5,000–6,000** more lines covered.

**Where to add tests (by impact):**

| Area        | Lines (approx) | Notes |
|------------|----------------|--------|
| Http       | ~11,780        | Controllers + middleware. Best covered by **Feature** tests (existing flows already help; add more for critical routes). |
| Services   | ~5,397         | Unit tests + mocks for APIs. Continue adding Service tests. |
| Models     | ~2,118         | Unit tests for scopes, accessors, constants. |
| Console    | ~2,071         | Unit tests that run commands (e.g. `Artisan::call`) with assertions. |
| Jobs       | ~108           | Dispatch job and assert `handle()` behavior. |
| Helpers    | ~26            | Already have DatabaseHelperTest; ensure it runs in full suite. |
| Others     | Notifications, Mail, Policies | Small; a few tests each. |

**Safe strategy:**

1. **Always run the full suite** (no filter) when generating the report: `./scripts/coverage-full.sh` or Docker equivalent.
2. Add tests in **small batches** (e.g. one service or one controller flow).
3. After each batch, run the **full** suite with coverage again and check `build/coverage/index.html` and per‑directory pages.
4. Prioritize: **Feature tests** for important HTTP flows, then **Unit** tests for Services, then Console, then Models/Jobs/Helpers.

---

## Quick reference

- **Real total %:** Run full suite with coverage → open `build/coverage/index.html` → first table, “Total” row.
- **Don’t:** Run `php artisan test --coverage` with a filter or single file and then treat that report as the project’s total.
- **Do:** Use `./scripts/coverage-full.sh` (or full Docker run) whenever you want to know or improve the real coverage number.
