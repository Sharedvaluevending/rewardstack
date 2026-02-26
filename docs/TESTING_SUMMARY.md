# Testing Setup Summary - Production Launch Ready

## ✅ What's Been Set Up

### 1. Testing Infrastructure
- ✅ PHPUnit configuration (`phpunit.xml`) with proper test environment
- ✅ Test database setup (SQLite in-memory for fast tests)
- ✅ Test runner script (`scripts/run-tests.sh`)
- ✅ CI/CD integration ready (tests run automatically on PR/push)

### 2. Test Suites Created

#### Unit Tests (Critical Business Logic)
- ✅ `tests/Unit/Services/GameServiceTest.php` - Game play logic, cheating detection, daily puzzles
- ✅ `tests/Unit/Services/PrizeServiceTest.php` - Reward calculations, tier multipliers, expiry logic

#### Feature Tests (End-to-End Flows)
- ✅ `tests/Feature/StripeWebhookTest.php` - Payment processing, subscription management
- ✅ `tests/Feature/PrintfulWebhookTest.php` - Printful order webhooks, shipping updates, order status management
- ✅ `tests/Feature/GamePlayFlowTest.php` - QR scanning, score submission, user stats

### 3. Test Data Factories
Created factories for all critical models:
- ✅ `GameFactory` - Game creation with various types
- ✅ `GamePlayFactory` - Game play records
- ✅ `GameSessionFactory` - Session management
- ✅ `QRCodeGameFactory` - QR code game configurations
- ✅ `PromotionFactory` - Promotions and discounts
- ✅ `SubscriptionPlanFactory` - Subscription plans
- ✅ `GameRewardFactory` - Reward records
- ✅ `OrderFactory` - Order creation for Printful testing

### 4. Documentation
- ✅ `docs/TESTING_STRATEGY.md` - Comprehensive testing strategy
- ✅ `docs/TESTING_QUICK_START.md` - Quick reference guide

## 🚀 Quick Start

### Run All Tests
```bash
cd /var/www/rewardstack
php artisan test
```

### Run Critical Tests Only
```bash
# Payment processing
php artisan test tests/Feature/StripeWebhookTest.php

# Game play logic
php artisan test tests/Unit/Services/GameServiceTest.php

# Reward system
php artisan test tests/Unit/Services/PrizeServiceTest.php
```

### Run with Coverage
```bash
# Full suite first, then coverage (safe workflow)
./scripts/coverage.sh

# Or directly (requires PCOV or Xdebug on host)
php artisan test --coverage --min=70
```
See `docs/COVERAGE_80.md` for reaching 80% total app coverage safely.

### Use Test Runner Script
```bash
./scripts/run-tests.sh
```

## 📋 Pre-Launch Checklist

### Critical Tests (Must Pass)
- [ ] **Payment Processing**: `php artisan test tests/Feature/StripeWebhookTest.php`
  - Subscription creation/update/deletion
  - Webhook signature verification
  - Payment failure handling

- [ ] **Game Play Logic**: `php artisan test tests/Unit/Services/GameServiceTest.php`
  - Score submission
  - Cheating detection (impossible scores, rapid replays)
  - Daily puzzle limits
  - User stats updates

- [ ] **Reward System**: `php artisan test tests/Unit/Services/PrizeServiceTest.php`
  - Reward creation
  - Tier calculations (gold/silver/bronze)
  - Expiry logic
  - Promotion application

- [ ] **End-to-End Flows**: `php artisan test tests/Feature/GamePlayFlowTest.php`
  - QR code scanning
  - Score submission
  - User stats updates
  - Daily puzzle prevention

- [x] **Printful Webhooks**: `php artisan test tests/Feature/PrintfulWebhookTest.php` ✅
  - Package shipped updates
  - Order status synchronization
  - Tracking number updates
  - Error handling for missing orders

### Coverage Goals
- [ ] **70%+ coverage** on critical services (GameService, PrizeService, StripeService)
- [ ] **80%+ coverage** on payment processing
- [ ] **60%+ coverage** overall
- [ ] **80% total app coverage** — see `docs/COVERAGE_80.md` for a safe workflow (run full suite first, add tests in small batches, then run coverage).

### Manual Testing (Required)
- [ ] Test Stripe webhooks in test mode
- [ ] Test game play flow on staging
- [ ] Test reward redemption
- [ ] Test subscription upgrades/downgrades
- [ ] Load testing (100+ concurrent users)

## 🔧 Next Steps (10-Day Timeline)

### Days 1-3: Core Testing
1. **Day 1**: Run all existing tests, fix any failures
2. **Day 2**: Add tests for any missing critical paths
3. **Day 3**: Achieve 70%+ coverage on critical services

### Days 4-6: Integration Testing
4. **Day 4**: Test Stripe webhooks with test mode ✅
5. **Day 5**: Test Printful webhooks ✅ **COMPLETED**
   - ✅ Created `PrintfulWebhookTest.php` with 13 test cases
   - ✅ Tests cover all webhook types: package_shipped, order_created, order_updated, order_failed, order_canceled, order_put_hold, product_synced, stock_updated
   - ✅ Tests verify token authentication, order status updates, tracking number updates
   - ✅ All 13 tests passing (39 assertions)
6. **Day 6**: End-to-end manual testing on staging

### Days 7-9: Performance & Security
7. **Day 7**: Load testing (use tools like Apache Bench or k6)
8. **Day 8**: Security testing (SQL injection, XSS, CSRF)
9. **Day 9**: Final test run, documentation review

### Day 10: Launch Prep
10. **Day 10**: Final smoke tests, monitor test suite, prepare rollback plan

## 📊 Test Coverage Report

After running tests with coverage, check:
```bash
# HTML report
open tests/coverage/index.html

# Console output
php artisan test --coverage
```

## 🐛 Troubleshooting

### Tests Failing?
1. Check database migrations: `php artisan migrate --env=testing`
2. Clear cache: `php artisan config:clear && php artisan cache:clear`
3. Regenerate autoload: `composer dump-autoload`

### Missing Factories?
- All critical factories are created
- If you need more, follow the pattern in existing factories

### Slow Tests?
- Tests use SQLite in-memory (fast)
- Consider parallel testing: `php artisan test --parallel`

## 📚 Resources

- **Testing Strategy**: `docs/TESTING_STRATEGY.md`
- **Quick Start**: `docs/TESTING_QUICK_START.md`
- **Laravel Testing**: https://laravel.com/docs/testing
- **PHPUnit Docs**: https://phpunit.de/documentation.html

## 🎯 Success Criteria

Your platform is ready for launch when:
- ✅ All critical tests passing
- ✅ 70%+ coverage on critical services
- ✅ Stripe webhooks tested in test mode
- ✅ Manual testing completed on staging
- ✅ Load testing shows acceptable performance
- ✅ Security testing passed

## 💡 Tips

1. **Run tests frequently** during development
2. **Fix failing tests immediately** - don't let them accumulate
3. **Write tests for new features** before implementing
4. **Use factories** for consistent test data
5. **Mock external services** (Stripe, Printful) in tests
6. **Keep tests fast** - use in-memory database
7. **Test edge cases** - especially for payment processing

## ⚠️ Safe Testing — Stripe & Printful Are Live

**Stripe and Printful APIs are live in production.** Tests must **never** call them.

- **`docs/SAFE_TESTING.md`** — Full guide: how we prevent live API calls, safe patterns, what requires mocks
- **Docker test env** — Uses fake keys only (`pk_test_fake`, `test_key`); no real keys
- **`SafeCommerceTest`** — Uses `Http::preventStrayRequests()` and config mocks
- **Smoke tests** — 15 tests covering app boot, webhooks (fake payloads), public routes, health, login

---

**Ready to test?** Start with: `php artisan test`

Good luck with your launch! 🚀

