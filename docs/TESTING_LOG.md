# Testing Log — What We've Done

Chronological record of test additions and coverage improvements. Update this as you add tests.

---

## 2026-02-14 — Smoke, Redemption, Play, Docs

### Added

**1. `docs/SAFE_TESTING.md`**
- Documents that Stripe/Printful/SendGrid are **never** called in tests
- Lists safe patterns: fake keys, `Http::preventStrayRequests()`, mocks
- Checklist for adding new tests

**2. `tests/Feature/SmokeTest.php` — expanded (15 → 17 tests)**
- Data provider for public routes: home, features, pricing, demo, privacy, terms, health, login, **register**
- `test_health_returns_json_with_ok`
- `test_login_page_loads`
- `test_register_page_loads`

**3. `tests/Feature/RedemptionControllerCoverageTest.php` — new (9 tests)**
- Short routes `/r/{code}` and `/t/{code}` — redirect to login when unauthenticated
- Short reward route renders QuickRedeemReward when authenticated with valid reward
- Short token route renders QuickRedeem when authenticated with valid token
- `test_redeem_reward_success_updates_status` — POST redeem reward
- `test_redeem_reward_rejects_expired_reward`
- `test_employee_redeem_index_shows_today_stats`
- `test_token_info_returns_punch_card_progress_for_valid_token` — employee.token.info
- `test_token_info_returns_404_for_invalid_token`

**4. `tests/Feature/PlayControllerTest.php` — added 1 test**
- `test_play_game_returns_404_for_invalid_game`

**5. PHPUnit config**
- Added `PRINTFUL_API_KEY=test_key` and `SENDGRID_API_KEY=test_key` to `phpunit.xml` and `phpunit.docker.xml`

**6. Doc updates**
- `TESTING_SUMMARY.md` — Safe Testing section
- `TEST_COVERAGE_STATUS_AND_PLAN.md` — link to SAFE_TESTING.md
- `COVERAGE_WHAT_DONE_AND_NEXT.md` — safe testing note

### Test counts (new tests only)

| File | Tests |
|------|-------|
| SmokeTest | 17 |
| RedemptionControllerCoverageTest | 9 |
| PlayControllerTest | 6 |

**Total new/updated:** ~30 tests, all safe (no Stripe/Printful live calls).

---

## 2026-02-14 (continued) — 80% Coverage Push

### Added

**1. AdminControllersCoverageTest — 8 new tests**
- `test_dashboard_returns_200_with_stats`
- `test_businesses_index_returns_200`
- `test_crm_index_returns_200`
- `test_qrcade_index_returns_200`
- `test_analytics_index_returns_200`
- `test_settings_index_returns_200`
- `test_merch_index_returns_200`
- `test_referrals_index_returns_200`

**2. HealthEndpointsTest — 2 new tests**
- `test_health_with_db_check_enabled_includes_db_ok`
- `test_health_response_has_service_env_version_time`

**3. ResubmitPendingPrintfulOrdersCommandTest — 1 new test**
- `test_command_dispatches_job_for_pending_paid_order` (Queue::fake, no Printful)

### Test counts

| File | Tests |
|------|-------|
| AdminControllersCoverageTest | 26 |
| HealthEndpointsTest | 3 |
| ResubmitPendingPrintfulOrdersCommandTest | 2 |

---

## 2026-02-14 (continued) — 80% Coverage Push #2

### Added

**1. AdminMerchControllerTest — new (3 tests)**
- `test_merch_index_returns_products_and_stats`
- `test_merch_orders_index_returns_200`
- `test_merch_order_show_returns_200`

**2. LoginRegisterTest — new (5 tests)**
- `test_login_page_returns_200`
- `test_login_succeeds_with_valid_credentials`
- `test_login_fails_with_invalid_password`
- `test_register_page_returns_200`
- `test_register_succeeds_with_valid_data`

**3. NotificationControllerTest — new (3 tests)**
- `test_mark_as_read_requires_auth`
- `test_mark_as_read_returns_back_when_authenticated`
- `test_mark_all_as_read_returns_back`

**4. AdminControllersCoverageTest — 2 more**
- `test_users_index_returns_200`
- `test_products_index_returns_200`

---

## 2026-02-14 (continued) — 80% Coverage Push #3

### Added

**1. ReferralLandingControllerTest — new (2 tests)**
- `test_referral_landing_redirects_to_pricing_when_invalid_code`
- `test_referral_landing_renders_page_when_valid_code`

**2. PublicBusinessControllerTest — new (2 tests)**
- `test_public_business_show_returns_200_for_active_business`
- `test_public_business_show_returns_404_for_inactive_business`

**3. PlayControllerApiTest — new (2 tests)**
- `test_start_session_returns_json_with_session_token`
- `test_start_session_fails_for_invalid_code`

**4. EmailUnsubscribeControllerTest — new (2 tests)**
- `test_unsubscribe_fails_for_invalid_params`
- `test_unsubscribe_renders_view_with_signed_url`

---

## 2026-02-14 (continued) — 80% Coverage Push #4

### Added

**1. PortalAdditionalCoverageTest — new (4 tests)**
- `test_rewards_show_returns_200_for_own_reward`
- `test_leaderboards_show_returns_200`
- `test_subscriptions_index_returns_200`
- `test_stripe_connect_redirects_or_returns_200`

**2. PublicPromoEmailControllerTest — new (2 tests)**
- `test_promo_email_returns_404_for_invalid_code`
- `test_promo_email_sends_and_returns_success`

---

## 2026-02-14 (continued) — 80% Coverage Push #5

### Added

**1. CampaignPromoControllerTest — new (6 tests)**
- `test_save_redirects_to_home_with_error_when_message_not_found`
- `test_save_redirects_to_home_with_error_when_promotion_not_found`
- `test_save_redirects_to_home_with_error_when_user_mismatch`
- `test_save_redirects_to_home_with_error_when_promotion_expired`
- `test_save_redirects_to_home_with_error_when_no_active_qr_code`
- `test_save_claims_promo_and_redirects_to_portal_scans`

**2. CleanupExpiredRewardsCommandTest — new (3 tests)**
- `test_command_exits_when_no_expired_rewards`
- `test_command_dry_run_shows_rewards_without_deleting`
- `test_command_deletes_expired_rewards_when_confirmed`

**3. NotifyExpiringRewardsCommandTest — new (3 tests)**
- `test_command_notifies_zero_when_no_expiring_rewards`
- `test_command_sends_notification_for_expiring_reward`
- `test_command_skips_non_portal_users`

**4. ScanFlowTest — 2 new tests**
- `test_promo_show_renders_for_valid_promotion_code`
- `test_promo_show_normalizes_customer_token_code`

### Test counts

| File | Tests |
|------|-------|
| CampaignPromoControllerTest | 6 |
| CleanupExpiredRewardsCommandTest | 3 |
| NotifyExpiringRewardsCommandTest | 3 |
| ScanFlowTest | 6 |

---

## 2026-02-14 — Coverage Run + Fixes

### Fixes (tests were failing)

**1. GameServiceTest — `it_detects_suspicious_high_scores`**
- **Issue:** `checkForCheating` received capped score (1000) instead of submitted (1500), so cheat detection never triggered.
- **Fix:** Store `$submittedScore` before capping; pass it to `checkForCheating` so scores above `max_score` are flagged.

**2. BillingControllerTest — subscribe tests (3 failures, 422)**
- **Issue:** Controller used `$request->user()->business_id`, but User has no `business_id` (relationship is `User hasOne Business` via `business.user_id`).
- **Fix:** Use `Business::where('user_id', $request->user()->id)` in BillingController::subscribe.

### Coverage run

- **Full coverage:** `./scripts/coverage-full.sh` + `./scripts/save-coverage-summary.sh`
- **Result:** **62.09%** line coverage (15,199 / 24,479 statements)
- **Saved to:** `docs/coverage-last/summary.json`, `UNCOVERED.md`, `COVERED.md`
- **Target:** 80% (gap: ~18 percentage points)

---

## 2026-02-14 — Additional Coverage Tests

### Added

**1. OnboardingQrTest — new (2 tests)**
- `test_constants_are_defined`
- `test_destination_url_returns_portal_join_url`

**2. BusinessCardQrTest — new (2 tests)**
- `test_constants_are_defined`
- `test_destination_url_returns_revenueqr_url`

**3. TrustHostsTest — new (1 test)**
- `test_hosts_returns_array`

**4. WebhookBasicAuthTest — new (5 tests)**
- `test_allows_request_when_token_matches`
- `test_allows_request_when_not_configured_in_non_production`
- `test_returns_401_when_basic_auth_required_but_not_provided`
- `test_returns_401_when_basic_auth_wrong_credentials`
- `test_allows_request_when_basic_auth_correct`

**5. BackfillUserPromoTokensCommandTest — new (4 tests)**
- `test_command_exits_success_when_no_scans`
- `test_command_creates_token_for_scan_without_token`
- `test_command_skips_when_token_already_exists`
- `test_command_filters_by_user_id_option`

### Test counts

| File | Tests |
|------|-------|
| OnboardingQrTest | 2 |
| BusinessCardQrTest | 2 |
| TrustHostsTest | 1 |
| WebhookBasicAuthTest | 5 |
| BackfillUserPromoTokensCommandTest | 4 |

**Total new:** 14 tests. Run `./scripts/coverage-full.sh` then `./scripts/save-coverage-summary.sh` to refresh coverage.

---

## 2026-02-14 — Console Command Coverage

### Added

**1. ReconcileStripeSubscriptionsCommandTest — new (4 tests)**
- `test_command_exits_when_stripe_not_configured`
- `test_command_downgrades_orphan_active_businesses`
- `test_command_dry_run_does_not_update_orphans`
- `test_command_filters_by_business_option`

**2. RunCrmAutomationsCommandTest — new (3 tests)**
- `test_command_processes_businesses_and_reports_count`
- `test_command_filters_by_business_id_option`
- `test_command_skips_inactive_businesses`

**3. ApproveReferralCommissionsCommandTest — new (3 tests)**
- `test_command_reports_none_when_no_pending_commissions`
- `test_command_approves_pending_commissions_older_than_30_days`
- `test_command_skips_pending_commissions_under_30_days`

### Test counts

| File | Tests |
|------|-------|
| ReconcileStripeSubscriptionsCommandTest | 4 |
| RunCrmAutomationsCommandTest | 3 |
| ApproveReferralCommissionsCommandTest | 3 |

**Total new:** 10 tests.

---

## How to update this log

When you add tests:
1. Add a new dated section (or append to today's)
2. List files changed and test counts
3. Note any new docs or config changes
