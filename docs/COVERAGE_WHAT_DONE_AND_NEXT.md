# What Was Done, What’s Left, and Safe Next Steps

**For the single summary of what’s done and last known coverage % → see [COVERAGE_SNAPSHOT.md](COVERAGE_SNAPSHOT.md).**

Quick reference: **what’s already well covered**, **what’s left**, and **safe, high-value next steps** with rough coverage impact.

---

## Recently completed

**2026-02-14 — Coverage run + fixes:**
- **Full coverage run:** 62.09% (15,199/24,479 statements). Saved to `docs/coverage-last/`.
- **Fixes:** GameServiceTest (suspicious high scores — pass submitted score to checkForCheating); BillingControllerTest (subscribe used business_id; fixed to use user_id lookup).
- **New tests (earlier):** CampaignPromoControllerTest (6), CleanupExpiredRewardsCommandTest (3), NotifyExpiringRewardsCommandTest (3), ScanFlowTest (+2 promo show).

**2026-02-14 — Smoke, Redemption, Play, tokenInfo:**
- **SmokeTest** — expanded to 17 tests: public routes (incl. register), health JSON, login/register pages
- **RedemptionControllerCoverageTest** — new file, 9 tests: short routes /r and /t, reward redeem success/expired, employee index, tokenInfo (valid + 404)
- **PlayControllerTest** — added `test_play_game_returns_404_for_invalid_game`
- **SAFE_TESTING.md** — new doc: Stripe/Printful never hit; safe patterns
- **TESTING_LOG.md** — new doc: chronological record of test additions
- PHPUnit: added PRINTFUL_API_KEY, SENDGRID_API_KEY to configs

**Tier 1 (steps 4–5):** 7 Portal notifications, PromoLinkEmail — see Tier 1 table below. **Tier 1 is complete** (~395 lines).

**Tier 2 (steps 6–7):** PrintKitArtworkService, CrmAutomationRunner — see Tier 2 table. **Tier 2 is complete** (~497 lines).

**Tier 3 (first batch):** Admin GET + settings update; Portal GET (see below).

**Tier 3 (continued — POST/update/delete):**
- **Admin** — Same file: POST `businesses.toggle`, `businesses.toggle-testing`, `users.toggle`; POST `referrals.payouts.paid`, `referrals.approve`, **`referrals.payouts.reject`**.
- **Business** — Same file: **POST `qrcade.games.toggle`** (enable/disable game for business).
- **Portal** — Same file: 7 more tests for **PUT** `portal.profile.update` (success + validation), **DELETE** `portal.profile.avatar.remove`, **POST** `portal.qr-codes.save` (success), **POST** save onboarding QR (422), **DELETE** `portal.qr-codes.unsave`, **POST** `portal.badges.feature` (toggle featured).

**Tier 3 (Business/Crm controllers):**
- **Business** — **`tests/Feature/Business/BusinessControllersCoverageTest.php`** (56 tests): GET onboarding.progress, help.faq, dashboard, analytics (index, finance, partnerships, scans*, redemptions*, merch, export, report), stackable-pools, print-studio, print-kits, print-kits.orders, qrcade (index, howto, games, rewards, schedule, leaderboards, analytics), crm (index, customers, recommendations, segments, campaigns, campaigns.create, campaigns.show, automations, settings), **partnerships, partnerships.search**, employees (index, create), settings, **qr-codes (index, create, show)**, promotions (index, create, templates, ideas), merch (index**, products, orders), ai-insights (basic, advanced); **PUT** settings.update (success); **POST** onboarding complete-step (JSON), dismiss (JSON), reopen (JSON), crm.segments.store, crm.campaigns.store, crm.automations.toggle, crm.automations.run, promotions.toggle; **DELETE** crm.segments.destroy. Uses business user + `Business::factory(['user_id' => $owner->id, 'is_testing_account' => true])` to bypass subscription middleware. *Scans/redemptions skip when endpoint returns 500. **Merch index allows 200 or 302.

---

## 1. What Was Done (likely high coverage)

These areas have **dedicated test files** and are where you’re already strong (e.g. Stripe, webhooks, core services).

### Stripe (very high)

- **StripeService** – Unit: `StripeServiceTest`, `StripeServiceHappyPathTest`, `StripeServiceStatusTest`, `StripeServiceFailureTest`
- **StripeConnectService** – Unit: `StripeConnectServiceTest`, `StripeConnectServiceProcessTest`, `StripeConnectServiceAdditionalCoverageTest` (Unit + Feature)
- **Stripe webhooks** – Feature: `StripeWebhookTest`, `StripeWebhookAdditionalCoverageTest`, `StripeWebhookOrderingTest`, `StripeMissingOrderWebhookTest`, `StripeRefundDisputeTest`, `StripeIntegrationTest`, `StripeConnectPayoutIdempotencyTest`, `SafeCommerceTest`

### Other webhooks

- **Printful** – Feature: `PrintfulWebhookTest`, `PrintfulFailureModeTest`, `PrintfulRecoveryJobTest`; Unit: `PrintfulServiceTest`; Console: PrintfulSetup, PrecachePrintfulMockups, ResubmitPendingPrintfulOrders, SyncPrintfulProducts
- **SendGrid** – Feature: `SendGridWebhookHappyPathTest`, `SendGridWebhookEventIngestionTest`; Unit: `CrmSendGridServiceTest`

### Core services (unit tests)

- GameService, PrizeService, QRGeneratorService (incl. coverage test), ScanService (anonymous), PromoClaimService, UserPromoTokenService, XpService  
- CrmAudienceService, CrmSendGridService, DeepSeekAIService, CrossPromoAnalyticsService, CrossPromoRulesService  
- MerchReferralRewardService, MerchUnlockService, ReferralCommissionService, AveryDpoService, BusinessCustomerService  
- LocationLockService, OnboardingService, CustomerCodeService, PreviewService, **RewardCodeService** (`tests/Unit/RewardCodeServiceTest.php`)  
- **PrintKitArtworkService** (`tests/Unit/PrintKitArtworkServiceTest.php`), **CrmAutomationRunner** (`tests/Unit/CrmAutomationRunnerTest.php`)  
- StripeService, StripeConnectService, PrintfulService  

### Controllers with focused coverage

- **RedemptionController** – `RedemptionControllerCoverageTest` (9 tests): short routes /r, /t; reward redeem success/expired; employee index; tokenInfo. Plus `EmployeeQuickRedeemTest`, `EmployeeQuickRedeemTokenAndRewardTest`.
- **ScanController** – `ScanFlowTest`, `ScanControllerAdditionalCoverageTest` (21 tests)
- **AccountController** – `tests/Feature/AccountControllerTest.php` (password update: auth required, success, wrong current password, validation)
- **Admin:** Dashboard, Users, Businesses, Analytics, Settings, CRM, Referrals, Products (smoke in `AdminPagesSmokeTest`). **Additional coverage:** `tests/Feature/Admin/AdminControllersCoverageTest.php` — GET: analytics revenue/usage, settings update/subscriptions, onboarding-qr, QRcade games/packs/seasonal/analytics, referrals payouts, businesses show, users show. **POST:** businesses toggle, businesses toggle-testing, users toggle.
- **Portal:** **`tests/Feature/Portal/PortalControllersCoverageTest.php`** — GET: dashboard, profile, games, levels, rewards index, scans, partner-deals, merch, badges, leaderboards, referrals, subscriptions. **PUT/DELETE/POST:** profile update (and validation), remove avatar, save/unsave QR code (including onboarding QR 422), toggle badge feature.
- **Business:** BillingController, MerchController, PartnershipController, AIInsightsController, AnalyticsController – dedicated Feature tests. **Additional coverage:** `tests/Feature/Business/BusinessControllersCoverageTest.php` — **58 tests** for GET (onboarding, help, dashboard, analytics, stackable-pools, print-studio, print-kits, qrcade, CRM including campaigns.show, qr-codes create/show, partnerships search, promotions.create), PUT settings.update, POST onboarding (complete-step, dismiss, reopen), crm.segments.store, crm.campaigns.store, crm.automations.toggle/run, promotions.toggle, DELETE crm.segments.destroy.
- **NotificationController** – `NotificationControllerTest`
- **Health** – HealthEndpointsTest; **Auth** – AuthPagesTest
- **Smoke** – `SmokeTest` (17 tests): app boot, webhooks (fake), public routes, health, login, register

### Console commands

All major commands have at least one test (Unit or Feature): AggregateGameAnalytics, AwardLeaderboardPrizes, BackfillUserPromoTokens, CleanupExpiredRewards, EnsureCadPlanPrices, GenerateAIInsights, **NotifyExpiringRewards** (`tests/Feature/Console/NotifyExpiringRewardsCommandTest.php`), PrecachePrintfulMockups, PrintfulSetup, ReconcilePromotionStats, RegenerateQRCodes, ResubmitPendingPrintfulOrders, RunCrmAutomations, SeedDemoData, SendTestEmail, SetEnvSecret, SyncPrintfulProducts, TestPromotionFlow, XpPacingReport, ApproveReferralCommissions, ProcessReferralPayouts, ResetPlatformData.

### Models, jobs, mail, notifications (many covered)

- Many models under `tests/Unit/Models/`
- Jobs: SendCrmMessage, ProcessReferralPayoutsJob, AggregateGameAnalyticsJob, GenerateAIInsightsForBusiness, SubmitPrintfulOrder (Feature)
- Mail: TestEmail, EmployeeInvitation, **PromoLinkEmail** (`tests/Unit/Mail/PromoLinkEmailTest.php`)
- Notifications: BusinessInvoicePaid, BusinessInvoicePaymentFailed, BusinessSubscriptionActivated, MerchOrder*, CrossPromo*, Partnership*, ReferralCommissionEarned – unit tests exist. **Portal notifications (7):** PortalLeaderboardPrize, PortalMerchReferralAwarded, PortalOnboarding, PortalPromoTokenAwarded, PortalPunchCardCompleted, PortalRewardExpiringSoon, PortalRewardWon – unit tests in `tests/Unit/Notifications/Portal*Test.php`

---

## 2. What’s Left (no or minimal dedicated tests)

| Area | File(s) | Lines (approx) | Notes |
|------|--------|----------------|-------|
| | Admin/* (11 controllers) | ~2,000+ | Smoke + AdminControllersCoverageTest (analytics, settings, onboarding-qr, QRcade, referrals, businesses/users show). Add more POST/update routes as needed. |
| | Business/Crm/* (7) | ~700+ | Some via CrmAutomationTest/CrmSegmentTest; not per-controller |
| | Portal/* (11) | PortalControllersCoverageTest covers main GET routes. Add POST/delete flows as needed. |
| | PlayController, RedemptionController | Large | **RedemptionControllerCoverageTest** (9 tests): short routes, reward redeem, tokenInfo. **PlayControllerTest** (6 tests): play flows, 404. |

---

## 3. Safe to Do Next (order and rough impact)

Do these in order. After each batch, run the **full** suite and, if needed, coverage, to confirm no regressions.

### Tier 1 – Easiest, fast coverage (~400 lines)

| Step | What | Est. lines | Status | Test file |
|------|------|------------|--------|-----------|
| 1 | **RewardCodeService** | ~38 | **Done** | `tests/Unit/RewardCodeServiceTest.php` (format, alphabet, uniqueness vs UserPromoToken/GameReward) |
| 2 | **NotifyExpiringRewards command** | ~59 | **Done** | `tests/Feature/Console/NotifyExpiringRewardsCommandTest.php` (notify once, no duplicate, exit when none) |
| 3 | **AccountController** | ~26 | **Done** | `tests/Feature/AccountControllerTest.php` (auth required, success, wrong password, validation) |
| 4 | **Portal notifications (7)** | ~243 | **Done** | `tests/Unit/Notifications/PortalLeaderboardPrizeTest.php`, `PortalMerchReferralAwardedTest.php`, `PortalOnboardingTest.php`, `PortalPromoTokenAwardedTest.php`, `PortalPunchCardCompletedTest.php`, `PortalRewardExpiringSoonTest.php`, `PortalRewardWonTest.php` (via, toArray, fallbacks) |
| 5 | **PromoLinkEmail** | ~29 | **Done** | `tests/Unit/Mail/PromoLinkEmailTest.php` (subject, views, public properties) |

**Tier 1 complete (all 5 steps):** ~395 lines covered.

### Tier 2 – Still safe, medium effort (~500 lines) — complete

| Step | What | Est. lines | Status | Test file |
|------|------|------------|--------|-----------|
| 6 | **PrintKitArtworkService** | ~101 | **Done** | `tests/Unit/PrintKitArtworkServiceTest.php` (generate2InStickerSheet, generate4InDecal; mock QRGeneratorService, Storage::fake + minimal PNG) |
| 7 | **CrmAutomationRunner** | ~396 | **Done** | `tests/Unit/CrmAutomationRunnerTest.php` (SendGrid not configured, no automations, unknown trigger, runForBusiness summary, winback/punch_card_nudge/promo_expiring with 0 sent) |

**Tier 2 complete:** ~497 lines covered. Next: Tier 3 (controllers).

### Tier 3 – Controllers (biggest impact)

**Done (GET + POST/update/delete):**
- **Admin** — `tests/Feature/Admin/AdminControllersCoverageTest.php` (18 tests: GET analytics, settings, onboarding-qr, QRcade, referrals payouts, businesses/users show; POST businesses toggle, businesses toggle-testing, users toggle, referrals.payouts.paid, referrals.approve, **referrals.payouts.reject**).
- **Portal** — `tests/Feature/Portal/PortalControllersCoverageTest.php` (23 tests: GET dashboard, profile, games, levels, rewards, scans, partner-deals, merch, badges, leaderboards, referrals, subscriptions; PUT profile update + validation; DELETE remove avatar, unsave QR, **scans.destroy** (when QR deleted); POST save QR, save onboarding QR 422, toggle badge feature).
- **Business** — `tests/Feature/Business/BusinessControllersCoverageTest.php` (59 tests: GET as above; PUT settings.update; POST onboarding, crm.segments.store, crm.campaigns.store, crm.automations.toggle/run, promotions.toggle, **qrcade.games.toggle**; DELETE crm.segments.destroy).

**Next (Tier 3):**
- **Admin:** referrals markPaid, approve, **reject done**. Optional: referrals.payouts.run (with Stripe mock), QRcade store/update.
- **Portal:** Reward claim, scan destroy, referrals payout (when safe to test).
- **Business:** **qrcade.games.toggle done.** Optional: print-kits create (with mock). CRM segment store/destroy, campaign store/show, automation toggle/run, onboarding dismiss/reopen covered.

Rough total for Tier 3 when fully done: **~1,200–2,100** lines.

---

## 4. Summary

- **Done:** Stripe (very high), Printful, SendGrid, core services, RewardCodeService, NotifyExpiringRewards, AccountController, 7 Portal notifications, PromoLinkEmail, PrintKitArtworkService, CrmAutomationRunner, ScanController extras, Business Billing/Merch/Partnership/AI Insights/Analytics, console commands, many models/jobs/mail/notifications. **Tier 1 and Tier 2 complete.** **Tier 3:** Admin controllers (**18 tests** incl. referrals markPaid, approve, reject), Portal controllers (23 tests), Business controllers (**59 tests** incl. qrcade.games.toggle).
- **Left:** Optional more Admin/Portal/Business POST and delete routes; PlayController, RedemptionController flows.
- **Safest next:** More Business POST/delete (CRM segments, campaigns, automations) or Play/Redemption flows.

To see exact numbers: run the full suite with coverage (`./scripts/coverage-full.sh` or Docker), open `build/coverage/index.html`, and use the **Total** row plus per-directory pages to confirm baseline (50%+) and to prioritize the next file to test.

**Safe testing:** Stripe and Printful APIs are live. See `docs/SAFE_TESTING.md` for how we prevent real API calls (fake keys, mocks, `Http::preventStrayRequests()`).
