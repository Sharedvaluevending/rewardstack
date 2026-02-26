# Test Coverage Snapshot — Single Source of Truth

**This is the one place to see how much testing is done and the last known coverage %.**  
Use it so we don’t re-test the same things and so any new conversation (or you in 2 days) can see what’s already done.

---

## The problem this fixes

- Saying “we did 56%” or “Stripe is 80%” is not a record. We need to **know which lines** are covered and which are not.
- **What’s the other 44%?** = the list of files and line numbers that the coverage report marked as not covered. That list is generated from the run, not from memory.
- **How do we avoid retesting the same thing?** = by having a committed record of what the tests already touch (the “covered” list and per-file %).

The **only** place that knows that is the **coverage report** from a full test run. So we **persist** it.

---

## Authoritative record: `docs/coverage-last/` (generated from the run)

After you run a **full** coverage run, run the save script. It reads `build/coverage/clover.xml` and writes:

| File | Purpose |
|------|--------|
| **`docs/coverage-last/summary.json`** | Machine-readable: total %, per-file %, and **uncovered line numbers** per file. So we know exactly what the X% is and what the other (100−X)% is. |
| **`docs/coverage-last/UNCOVERED.md`** | The “other X%”: every file under 100%, sorted worst first, with **uncovered line numbers**. Use this to decide what to test next. |
| **`docs/coverage-last/COVERED.md`** | The “X%”: files that already have coverage. Use this so we **don’t retest the same code**. |

**Workflow:**

1. Run **full** coverage (no filter): `./scripts/coverage-full.sh` or `php artisan test --coverage`
2. Run: `./scripts/save-coverage-summary.sh` (or `php scripts/save-coverage-summary.php`)
3. **Commit** `docs/coverage-last/` so the record is in the repo

Then any future session (or you in 2 days) can open `summary.json` or `UNCOVERED.md` / `COVERED.md` and know exactly what’s tested and what isn’t — no guessing, no “I think we did 80% on Stripe.”

**Right now:** If `docs/coverage-last/summary.json` exists, the **true** numbers (and the exact “other X%”) are in there. If the last run was partial, that file will show a low %; run a full coverage and re-run the save script to refresh.

---

## Quick numbers (from last saved run or manual note)

| What | Number |
|------|--------|
| **Line coverage** | See `docs/coverage-last/summary.json` → `line_coverage_percent` (or note: **62.09%** from 2026-02-14 full run) |
| **Assertions** | From test run output (e.g. **3,075**) |
| **Stripe (and similar)** | See `summary.json` → `files["app/Services/StripeService.php"].percent` etc. |
| **Target** | 80% |

---

## Why the HTML report sometimes shows ~10%

**`build/coverage/index.html` is overwritten every time you run tests with `--coverage`.**

- If you run **one file** or use **`--filter`**, only that slice is measured and the report shows a **fake low %** (e.g. 10%).
- The **real** project coverage is only correct when you run the **full** suite with no filter:  
  `./scripts/coverage-full.sh` or `php artisan test --coverage` (all tests).

So: **ignore the HTML report unless you just ran a full coverage run.** For “how much is done” between full runs, use this doc and the inventory below.

---

## Last full coverage run — where the numbers live

- **Line coverage % and per-file %:** `docs/coverage-last/summary.json` (after you run full coverage and then `./scripts/save-coverage-summary.sh`).
- **Assertions:** From the test run output when you ran coverage (e.g. 3,075). Not stored in the summary; note it if you want.
- **Target:** 80%.

---

## What the tested half actually means

The code that those **3,075 assertions** touched is what’s in “green” — the tested half. In practice that means:

- **Happy path:** The most common user actions are covered (e.g. user logs in correctly, user wins a game, webhook is received).
- **Core logic:** PrizeService and GameService (and similar) are **well above average** — the “math” of the app is in good shape.
- **Validation:** The app is unlikely to crash when users do what they’re supposed to do.

So the 56.38% isn’t random: it’s the happy path, core logic, and validation. The remaining gap is the less common paths and edge cases.

---

## Documented coverage (what’s already done — no full run needed)

This is the **progressive log**: what we know is covered from the tests we added. Use it when you haven’t run a full coverage recently.

### By area (from test inventory)

| Area | Status | Details |
|------|--------|--------|
| **Stripe** | Very high (~80–100%) | StripeService, StripeConnectService, webhooks (multiple Feature tests). |
| **Webhooks** | High (~89%+) | Stripe, Printful, SendGrid — happy path, failures, idempotency. |
| **Portal controllers** | 23 tests | `tests/Feature/Portal/PortalControllersCoverageTest.php` — dashboard, profile, games, rewards, scans, partner-deals, merch, badges, leaderboards, referrals, subscriptions, profile update, avatar, save/unsave QR, scans.destroy, badge feature. |
| **Admin controllers** | 18 tests | `tests/Feature/Admin/AdminControllersCoverageTest.php` — analytics, settings, onboarding-qr, QRcade, referrals payouts, businesses/users show, POST toggles, referrals.payouts.paid, referrals.approve, **referrals.payouts.reject**. |
| **Business controllers** | 59 tests | `tests/Feature/Business/BusinessControllersCoverageTest.php` — dashboard, CRM, qr-codes, promotions, partnerships, qrcade (incl. **qrcade.games.toggle**), etc. |
| **RedemptionController** | 9 tests | RedemptionControllerCoverageTest — short routes /r, /t; reward redeem; tokenInfo. Plus EmployeeQuickRedeemTest, EmployeeQuickRedeemTokenAndRewardTest. |
| **ScanController** | 21 tests | ScanFlowTest, ScanControllerAdditionalCoverageTest. |
| **AccountController** | Covered | Password update (auth, success, wrong password, validation). |
| **Console commands** | Most covered | AggregateGameAnalytics, AwardLeaderboardPrizes, BackfillUserPromoTokens, CleanupExpiredRewards, NotifyExpiringRewards, PrintfulSetup, RunCrmAutomations, etc. |
| **Services (unit)** | Many covered | GameService, PrizeService, QRGeneratorService, CrmSendGridService, CrmAutomationRunner, PrintKitArtworkService, RewardCodeService, StripeService, PrintfulService, etc. |
| **Portal notifications (7)** | Unit tested | PortalLeaderboardPrize, PortalMerchReferralAwarded, PortalOnboarding, PortalPromoTokenAwarded, PortalPunchCardCompleted, PortalRewardExpiringSoon, PortalRewardWon. |
| **Mail** | TestEmail, EmployeeInvitation, PromoLinkEmail | Unit/Feature. |
| **Models / Jobs** | Many | See `tests/Unit/Models/`, jobs like SendCrmMessage, AggregateGameAnalyticsJob, etc. |

### Tier summary (from coverage plan)

- **Tier 1:** Done (~395 lines) — RewardCodeService, NotifyExpiringRewards, AccountController, 7 Portal notifications, PromoLinkEmail.
- **Tier 2:** Done (~497 lines) — PrintKitArtworkService, CrmAutomationRunner.
- **Tier 3:** Admin (18), Portal (23), Business (61), **PlayController (6)**, **RedemptionController (9)**, **Smoke (17)** controller/smoke tests in place.

---

## Plan to 80% (record what we do)

- **Current (from last full run):** **62.09%** line coverage (15,199/24,479 statements). Happy path, core logic (PrizeService, GameService), validation well covered.
- **Target:** 80% line coverage.
- **Gap:** ~18 percentage points to 80% ≈ focus on remaining controller paths, edge cases, and any 0% console/HTTP we can safely hit.
- **Just done (2026-02-14):** SmokeTest (17), RedemptionControllerCoverageTest (9), PlayController (6), AdminControllersCoverageTest (+8), HealthEndpointsTest (+2), ResubmitPendingPrintfulOrdersCommandTest (+1), CampaignPromoControllerTest (6), CleanupExpiredRewardsCommandTest (3), NotifyExpiringRewardsCommandTest (3), ScanFlowTest (+2 promo show). Fixed GameServiceTest (suspicious high scores), BillingControllerTest (subscribe used wrong business lookup). **Full coverage run: 62.09%** (15,199/24,479). SAFE_TESTING.md, TESTING_LOG.md.
- **Next (safe):** Optional Admin (referrals.payouts.run with Stripe mock), Portal (reward claim, referrals payout with mock), Business (print-kits create with mock). Then run **full coverage** and **save-coverage-summary** to refresh the record.

---

## When you add new tests (keep this progressive)

1. **When you run a full coverage run**  
   Run `./scripts/save-coverage-summary.sh` and **commit `docs/coverage-last/`**. That is the only way the record of “what lines are covered” gets updated. No more guessing.

2. **Add to this snapshot** (optional)  
   In the “Documented coverage” table, add or update the row for that area (e.g. “Business controllers — 60 tests”).

3. **Add to** `docs/TESTING_LOG.md` — dated entry with files changed and test counts.

4. **Optionally add to** `docs/COVERAGE_WHAT_DONE_AND_NEXT.md`  
   In “Recently completed”, add one line: what you added and which test file.

**To know “what’s the other X%” or “did we already test this file?”:** Open `docs/coverage-last/UNCOVERED.md` and `docs/coverage-last/COVERED.md` (or `summary.json`). They are generated from the run; they are the record.

---

## Where else to look

- **Detail on what’s done / what’s next:** `docs/COVERAGE_WHAT_DONE_AND_NEXT.md`
- **Chronological log of test additions:** `docs/TESTING_LOG.md`
- **Gaps and under-covered areas:** `docs/COVERAGE_GAPS.md`
- **Plan to 80%, Docker, scripts:** `docs/TEST_COVERAGE_STATUS_AND_PLAN.md`
- **Real coverage number (only after full run):** `build/coverage/index.html` → Total row
