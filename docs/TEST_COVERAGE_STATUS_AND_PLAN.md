# Test Coverage Status & Plan to 80%

**For the single summary of what’s done and last known coverage % → see [COVERAGE_SNAPSHOT.md](COVERAGE_SNAPSHOT.md).**

This document summarizes **what tests exist**, **how far we are**, **Docker test environment**, and a **concrete plan to reach 80%** app coverage.

---

## 1. Current Test Inventory

### PHP (Laravel / PHPUnit)

| Category | Count | Notes |
|----------|--------|------|
| **Feature tests** | ~125 files | HTTP flows, console commands, webhooks, scans, portal, business, CRM, Stripe, Printful, cross-promo, etc. |
| **Unit tests** | ~55 files | Services, models, jobs, mail, notifications, policies, helpers, console (focused unit) |
| **Test methods** | 400+ | Across all PHP test files |

**Infrastructure**

- `phpunit.xml` – host runs (SQLite in-memory, coverage to `build/coverage`)
- `phpunit.docker.xml` – Docker runs (MySQL, coverage to `build/coverage`)
- Scripts: `scripts/coverage-full.sh`, `scripts/coverage.sh`, `scripts/run-tests.sh`

### JavaScript (Vitest)

| Item | Count |
|------|--------|
| Test files | 2 |
| Components covered | `PunchCardRedemption.test.js`, `CurrencyDisplay.test.js` |

- Config: `vitest.config.js` (jsdom, coverage: text/json/html)
- Run: `npm run test` / `npm run test:coverage`

### Docker Test Environment

- **Compose file:** `docker-compose.testing.yml`
- **Services:** `app`, `db` (MySQL 8.0), `redis`
- **Runner script:** `./scripts/docker-test.sh`
  - Brings up `db` and `redis`
  - Waits for MySQL
  - Runs: `./vendor/bin/phpunit -c phpunit.docker.xml` inside `app`
  - Tears down with `down -v`
- **Env:** `APP_ENV=testing`, MySQL test DB, fake Stripe/Printful/SendGrid keys, `QUEUE_CONNECTION=sync`, no workers.

**Coverage in Docker:** Run the full suite with coverage in the container (image must have PCOV or Xdebug):

```bash
docker compose -f docker-compose.testing.yml up -d db redis
docker compose -f docker-compose.testing.yml run --rm app ./vendor/bin/phpunit -c phpunit.docker.xml
# Then open build/coverage/index.html
docker compose -f docker-compose.testing.yml down -v
```

---

## 2. What’s Covered (Summary)

### Well covered

- **Webhooks:** Stripe, Printful, SendGrid (happy path, events, failures, idempotency)
- **Console commands:** Most have Feature tests (e.g. AggregateGameAnalytics, AwardLeaderboardPrizes, BackfillUserPromoTokens, CleanupExpiredRewards, EnsureCadPlanPrices, GenerateAIInsights, PrecachePrintfulMockups, PrintfulSetup, ReconcilePromotionStats, RegenerateQRCodes, ResubmitPendingPrintfulOrders, RunCrmAutomations, SeedDemoData, SendTestEmail, SetEnvSecret, SyncPrintfulProducts, TestPromotionFlow, XpPacingReport, ApproveReferralCommissions)
- **Services (unit):** GameService, PrizeService, StripeService, StripeConnectService, PrintfulService, QRGeneratorService, CrmSendGridService, CrmAudienceService, DeepSeekAIService, CrossPromoAnalyticsService, CrossPromoRulesService, MerchReferralRewardService, MerchUnlockService, ReferralCommissionService, AveryDpoService, BusinessCustomerService, and others
- **Models:** Many have unit tests (Game, GamePlay, GameSession, Leaderboard, Promotion, QRCode, User, etc.)
- **Jobs:** SendCrmMessage, ProcessReferralPayoutsJob, AggregateGameAnalyticsJob, GenerateAIInsightsForBusiness (unit/feature)
- **Business area:** Billing, Merch, Partnership, AI Insights, Analytics, QRcade, promotions, employees, onboarding, stackable pools, CRM smoke
- **Portal:** Scans, rewards, merch ambassador, partner deals, referrals smoke, leaderboards, games, badges
- **Public / scans:** Scan flow, QR codes, public promo pages, stackable pool scan
- **Auth:** Auth pages, employee invite/accept
- **Mail / Notifications:** TestEmail, EmployeeInvitation; many notification classes have unit tests
- **Policies:** OrderPolicy
- **Helpers:** DatabaseHelper

### Under-covered or missing

- **Services:** `PrintKitArtworkService` – no dedicated test
- **Http (controllers):** Largest chunk of app (~11,780 lines). Many controllers only hit indirectly:
  - **Admin:** Analytics, BusinessManagement, Crm, Dashboard, MerchManagement, OnboardingQr, Product, QRcadeAdmin, ReferralManagement, Settings, UserManagement – mostly smoke only
  - **Business/Crm:** Automation, Campaign, Customer, Dashboard, Recommendations, Segment, Settings – some coverage via CRM tests but not per-controller
  - **Portal:** BusinessSubscriptionController, PartnerDealsController, PortalController, PortalRewardController, PortalMerchController, PortalSavedQRCodeController, PortalScanController, ReferralController, StripeConnectController – some flows covered, not exhaustive
  - **AccountController,** **PlayController,** **RedemptionController,** **ScanController** – large controllers; add focused Feature tests for critical actions
- **Notifications:** PortalLeaderboardPrize, PortalMerchReferralAwarded, PortalOnboarding, PortalPromoTokenAwarded, PortalPunchCardCompleted, PortalRewardExpiringSoon, PortalRewardWon – verify in coverage report and add tests if 0%
- **Middleware:** Mostly exercised via requests; consider unit tests for critical ones (e.g. role, subscription, webhook auth)
- **JS/frontend:** Only 2 component tests; more components and flows could be tested with Vitest

---

## 3. Current Coverage Number

- **Single place for “how much is done” and last known %:** See **[COVERAGE_SNAPSHOT.md](COVERAGE_SNAPSHOT.md)**. Update that file when you run a full coverage or add tests.
- **Baseline (full suite):** The platform has been at **50%+** line coverage when the **full** test suite is run with coverage. That is the real baseline.
- **Why the report sometimes shows ~10% or ~5%:** The file `build/coverage/index.html` is **overwritten every time** you run tests with `--coverage`. If the last run was a **partial** run (e.g. one test file or `--filter`), only that slice is measured and the total shows very low. That does **not** mean the project is at that %.
- **To get the real %:** Run the full suite with coverage (Host: `./scripts/coverage-full.sh` or Docker; see "Coverage in Docker" above). Then open `build/coverage/index.html` → **Total** row, and **update COVERAGE_SNAPSHOT.md** with the date and %.
- **Target:** App size ~22,570 lines. **80% ≈ 17,416 lines** covered. From a 50%+ baseline, the gap to 80% is roughly **5,000–6,000** more lines to cover.

---

## 4. Plan to Reach 80% Coverage

### Principle

- **Always** run the **full** suite when generating coverage (`./scripts/coverage-full.sh` or full Docker run).
- Add tests in **small batches** (e.g. one service, one controller flow).
- After each batch, run the full suite with coverage and check `build/coverage/index.html` and per-directory pages.

### Phase 1: Establish baseline and fix gaps (Week 1)

1. **Get real baseline**
   - Run full suite with coverage (host or Docker).
   - Open `build/coverage/index.html`, note **Total** line coverage % and which namespaces are lowest (Http, Console, Services, Jobs, etc.).

2. **Quick wins**
   - **PrintKitArtworkService:** Add `tests/Unit/PrintKitArtworkServiceTest.php` (mock external calls).
   - **Portal notifications:** Add unit tests for any with 0% (PortalLeaderboardPrize, PortalRewardWon, etc.).
   - **AccountController:** Add Feature test for key account/settings routes (e.g. profile, password).

3. **Console**
   - Confirm all commands are covered by at least one test (Unit or Feature). Add minimal tests for any with 0% (e.g. `NotifyExpiringRewards` if not covered).

**Target after Phase 1:** Baseline documented; a few percent gain; no new 0% in Services/Console.

---

### Phase 2: Controllers and HTTP (Weeks 2–4)

Controllers are the largest share of lines. Prioritize by risk and size.

1. **Critical / high-traffic controllers**
   - **ScanController** (1,267 lines), **PortalScanController** (1,555), **RedemptionController** (1,386): Add Feature tests for main actions (scan, redeem, error paths).
   - **PlayController** (620): Add tests for play flow, score submit, validation.
   - **StripeWebhookController** (565), **PrintfulWebhookController** (334), **SendGridWebhookController** (207): Already well covered; add only for any remaining branches.

2. **Business area**
   - **AnalyticsController** (1,282), **QRCodeController** (969), **AIInsightsController** (928), **QRcadeController** (889), **PartnershipController** (886), **MerchController** (1,072), **DashboardController** (649): Add or extend Feature tests for main endpoints (index, show, store, update, destroy) and error cases.
   - **Business/Crm/** (Automation, Campaign, Customer, Dashboard, Recommendations, Segment, Settings): One Feature test file per controller covering main actions.

3. **Portal**
   - **PortalController** (499), **PortalRewardController** (361), **PortalMerchController** (155), **PortalLeaderboardController** (111), **PortalGameController** (115), **PortalSavedQRCodeController** (102): Add Feature tests for key routes (dashboard, rewards list, redeem, leaderboard, games, saved QR codes).

4. **Admin**
   - Add focused Feature tests for Admin controllers (Analytics, BusinessManagement, MerchManagement, QRcadeAdmin, ReferralManagement, etc.) for at least main read/write actions.

**Target after Phase 2:** Http namespace coverage clearly up; total moving toward ~65–70%.

---

### Phase 3: Services, Jobs, Models (Weeks 4–5)

1. **Services**
   - Open `build/coverage/index.html` → Services. For any service below 70%, add or expand unit tests (mock APIs, focus on branches and edge cases).
   - **GameService** (1,235), **QRGeneratorService** (2,176), **PrintfulService** (1,618), **PreviewService** (765), **XpService** (383), **DeepSeekAIService** (384), **StripeConnectService** (420), **CrmAutomationRunner** (396): Prioritize by uncovered lines in report.

2. **Jobs**
   - Ensure each Job’s `handle()` is exercised by at least one test (Unit or Feature). Add tests for **SubmitPrintfulOrder** and any Maintenance jobs if still low.

3. **Models**
   - For any model with 0% or very low coverage, add Unit tests for scopes, accessors, and important constants.

**Target after Phase 3:** Services and Jobs in good shape; total approaching or at 80%.

---

### Phase 4: Polish and maintain (Ongoing)

1. **Middleware**
   - Add unit tests for critical middleware (e.g. EnsureActiveSubscription, CheckRole, webhook verification).

2. **Frontend (Vitest)**
   - Add tests for important JS components and flows (e.g. redemption, forms, display components). Aim for 80% on critical JS used in reward/scan flows.

3. **CI and gates**
   - In CI, run full test suite; optionally run with coverage and fail or warn if total drops below 80% or if critical namespaces drop below a set threshold.

4. **Docs**
   - Keep `docs/COVERAGE_GAPS.md` in sync with reality (remove items that now have tests; add new gaps found from coverage report).

---

## 5. Quick Reference

| Goal | Command / action |
|------|-------------------|
| Run all PHP tests (no coverage) | `php artisan test` |
| Run PHP tests in Docker | `./scripts/docker-test.sh` |
| Get real coverage % (host) | `./scripts/coverage-full.sh` then open `build/coverage/index.html` |
| Get real coverage % (Docker) | `docker compose -f docker-compose.testing.yml up -d db redis` then `docker compose -f docker-compose.testing.yml run --rm app ./vendor/bin/phpunit -c phpunit.docker.xml`; open `build/coverage/index.html` |
| Safe workflow (test first, then coverage) | `./scripts/coverage.sh` |
| Run JS tests | `npm run test` |
| JS coverage | `npm run test:coverage` |

---

## 6. Related Docs

- `docs/COVERAGE_PLAN_80.md` – Why the report can show ~0.76%, how to get real %, path to 80%.
- `docs/COVERAGE_80.md` – Safe workflow (small batches, prioritize areas).
- `docs/COVERAGE_GAPS.md` – List of previously missing tests (update as gaps are filled).
- `docs/SAFE_TESTING.md` – **Stripe/Printful are live** — how we keep tests safe (fake keys, mocks, no real API calls).
- `docs/TESTING_LOG.md` – Chronological record of test additions.
- `docs/TESTING_SUMMARY.md` – Test setup, quick start, critical tests, coverage goals.
