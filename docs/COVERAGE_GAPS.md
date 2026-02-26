# What Hasn’t Been Covered and Tested

**For the single summary of what’s done and last known coverage % → see [COVERAGE_SNAPSHOT.md](COVERAGE_SNAPSHOT.md).**

This doc lists app code that has **no or minimal** dedicated tests. The `build/coverage/index.html` report is often from a **partial run** (e.g. ~10% total), so treat it as “which files exist” rather than true project coverage. For real total %, run the full suite: `./scripts/coverage-full.sh`, then update COVERAGE_SNAPSHOT.md with the % and date.

**See also:** `docs/TEST_COVERAGE_STATUS_AND_PLAN.md` for full inventory and plan to 80%.

---

## 1. Services — no or minimal dedicated unit tests

**Services that now have unit tests:**  
AveryDpoService, CrmAudienceService, CrmSendGridService, CrossPromoAnalyticsService, **CrmAutomationRunner** (`tests/Unit/CrmAutomationRunnerTest.php`), DeepSeekAIService, MerchReferralRewardService, **PrintKitArtworkService** (`tests/Unit/PrintKitArtworkServiceTest.php`), RewardCodeService (`tests/Unit/RewardCodeServiceTest.php`), and most others — see `tests/Unit/`.

---

## 2. Console commands

**Commands that now have tests (Unit or Feature):**  
AggregateGameAnalytics, AwardLeaderboardPrizes, BackfillUserPromoTokens, CleanupExpiredRewards, EnsureCadPlanPrices, GenerateAIInsights, **NotifyExpiringRewards** (`tests/Feature/Console/NotifyExpiringRewardsCommandTest.php`), PrecachePrintfulMockups, PrintfulSetupCommand, ReconcilePromotionStats, RegenerateQRCodes, ResubmitPendingPrintfulOrders, RunCrmAutomations, SeedDemoData, SendTestEmail, SetEnvSecret, SyncPrintfulProducts, TestPromotionFlow, XpPacingReport, ApproveReferralCommissions, ProcessReferralPayouts, ResetPlatformData.

---

## 3. Jobs

**Jobs with tests:**  
SendCrmMessage (Unit), SubmitPrintfulOrder (Feature), ProcessReferralPayoutsJob (Unit), AggregateGameAnalyticsJob (Unit), GenerateAIInsightsForBusiness (Unit). Check coverage report for any remaining uncovered `handle()` branches.

---

## 4. Http — controllers and middleware

Many controllers are hit indirectly by Feature tests (smoke tests, flows). The following are **likely under-covered** or have no dedicated test file:

- **AccountController** — covered by `tests/Feature/AccountControllerTest.php` (password update).
- **Admin/** — Smoke in AdminPagesSmokeTest; **additional coverage** in `tests/Feature/Admin/AdminControllersCoverageTest.php` (GET: analytics, settings, onboarding-qr, QRcade, referrals payouts, businesses/users show; POST: businesses toggle, businesses toggle-testing, users toggle, referrals.payouts.paid, referrals.approve, **referrals.payouts.reject**). Add runAutoPayouts (with mock), merch sync, etc., as needed.
- **Business/Crm/** — **Covered** by `tests/Feature/Business/BusinessControllersCoverageTest.php`: GET index, customers, recommendations, segments, campaigns, campaigns.create, campaigns.show, automations, settings; POST segments.store, campaigns.store, automations.toggle, automations.run; DELETE segments.destroy. Add campaigns.queue/destroy as needed.
- **Business/** — **Covered** (GET) in `BusinessControllersCoverageTest.php`: Dashboard, Help (faq), Onboarding (progress), Analytics (all sub-routes), StackablePool, PrintStudio, PrintKit, QRcade, Partnerships, Employees (index, create), Settings (GET + PUT update), QRCode (index), Promotions (index), Merch (index, products, orders), AI Insights (basic, advanced). BillingController, MerchController, PartnershipController, AIInsightsController have dedicated tests; HelpController (HelpFaqTest), OnboardingController (OnboardingApiTest), StackablePools page test exist.
- **Portal/** — **Covered** by `tests/Feature/Portal/PortalControllersCoverageTest.php`: GET (dashboard, profile, games, levels, rewards, scans, partner-deals, merch, badges, leaderboards, referrals, subscriptions); PUT profile update; DELETE remove avatar, unsave QR, **scans.destroy** (when QR/promo deleted); POST save/unsave QR (including onboarding QR 422), toggle badge feature. Add reward claim, referrals payout as needed.
- **Webhooks/** — PrintfulWebhookController, SendGridWebhookController (tested), StripeWebhookController (tested).
- **Middleware** — Most middleware are exercised only via request tests; no dedicated unit tests for each.

---

## 5. Models, Mail, Notifications, Policies, Helpers

- **Models** — Many have Unit tests (e.g. under `tests/Unit/Models/`). Check coverage report after a full run for any model with 0% and add tests for scopes/accessors/constants.
- **Mail** — TestEmail, EmployeeInvitation, **PromoLinkEmail** (`tests/Unit/Mail/PromoLinkEmailTest.php`) have tests.
- **Notifications** — Many have Unit tests. **Portal notifications (7)** now covered: PortalLeaderboardPrize, PortalMerchReferralAwarded, PortalOnboarding, PortalPromoTokenAwarded, PortalPunchCardCompleted, PortalRewardExpiringSoon, PortalRewardWon (`tests/Unit/Notifications/Portal*Test.php`). Verify full run for any remaining gaps.
- **Policies** — OrderPolicy has test; others (if any) may be untested.
- **Helpers** — DatabaseHelper has test; ensure it runs in full suite.

---

## 6. How to use this list

1. **Get real coverage:** Run `./scripts/coverage-full.sh`, then open `build/coverage/index.html` and drill into `Console/Commands`, `Services`, `Jobs`, `Http/Controllers` to see actual uncovered lines.
2. **Prioritize:** Add tests in this order for impact: Feature tests for critical HTTP flows → Unit tests for Services (with mocks) → Console command tests → Job handle() tests → remaining Controllers/Models.
3. **Safety:** Add tests in small batches and re-run the **full** suite with coverage to confirm the total % and that no regressions appear.

See also: `docs/TEST_COVERAGE_STATUS_AND_PLAN.md`, `docs/COVERAGE_PLAN_80.md`, `docs/COVERAGE_80.md`, `docs/TESTING_SUMMARY.md`.
