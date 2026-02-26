# Safe Testing — Stripe, Printful, SendGrid Never Hit Live APIs

**Critical:** Stripe and Printful APIs are **live** in production. Tests must **never** call them.

This doc explains how we keep tests safe and what patterns to follow.

---

## 1. How We Prevent Live API Calls

### Environment (Always in Tests)

| Variable | Test Value | Purpose |
|---------|------------|---------|
| `APP_ENV` | `testing` | Forces test mode |
| `STRIPE_KEY` | `pk_test_fake` | Stripe rejects invalid keys; no real calls |
| `STRIPE_SECRET` | `sk_test_fake` | Same |
| `STRIPE_WEBHOOK_SECRET` | `whsec_test_fake` | Webhook signature verification uses this |
| `STRIPE_CONNECT_ENABLED` | `false` (Docker) | Disables Connect flows |
| `PRINTFUL_API_KEY` | `test_key` | Printful rejects; no real orders |
| `SENDGRID_API_KEY` | `test_key` | SendGrid rejects; no real emails |

**Where set:**
- `phpunit.xml` (host runs)
- `phpunit.docker.xml` (Docker runs)
- `docker-compose.testing.yml` (Docker env)

### Code-Level Safety

1. **`Http::preventStrayRequests()`** — Used in `SafeCommerceTest` and any test that might trigger HTTP. Any unexpected outbound HTTP call fails the test.
2. **`Http::fake()`** — Stub expected Printful/SendGrid calls with fake responses.
3. **`$this->mock(PrintfulService::class)`** — Mock the service so it never calls the real API.
4. **`$this->mock(StripeService::class)`** — Same for Stripe.

---

## 2. Tests That Touch Commerce (Safe Patterns)

| Test File | What It Does | How It Stays Safe |
|-----------|--------------|-------------------|
| `SafeCommerceTest` | Referral commission, Printful order payload, Stripe webhook, Avery CSV | `Config::set` fake keys + `Http::preventStrayRequests()` |
| `StripeWebhookTest` | Webhook handling | Fake payloads, signature disabled or mocked |
| `PrintfulWebhookTest` | Webhook handling | `config(['services.printful.webhook_token' => null])` for no-auth flow; fake payloads |
| `PrintfulSetupCommandTest` | Setup command | `$this->mock(PrintfulService::class)` |
| `PrecachePrintfulMockupsCommandTest` | Precache command | `$this->mock(PrintfulService::class)` |
| `StripeConnectPayoutIdempotencyTest` | Payout idempotency | Injects `\Mockery::mock(\Stripe\StripeClient::class)` |

---

## 3. What Is Safe to Test Without Mocks

- **Public routes:** `/`, `/features`, `/pricing`, `/demo`, `/privacy`, `/terms`, `/health` — no external APIs
- **Auth flows:** Login, register, password reset — no Stripe/Printful
- **Portal/Business/Admin GET routes:** With `is_testing_account` or mocked subscription — no live billing
- **Redemption flows:** Employee redeem, punch cards, promotions — no Stripe/Printful (merch checkout is separate)
- **Scan flows:** QR scan, promotion show — no external APIs
- **Console commands:** Most use mocks or fake keys; never use real keys

---

## 4. What Requires Mocks or Fakes

- **Stripe Checkout / Payment Intents** — Mock `StripeService` or `Stripe\StripeClient`
- **Printful order creation** — Mock `PrintfulService` or use `Http::fake()` for `api.printful.com`
- **SendGrid email** — Mock `CrmSendGridService` or use `Http::fake()` for `api.sendgrid.com`
- **Stripe Connect payouts** — Mock `StripeConnectService` or inject mock `StripeClient`

---

## 5. Docker Test Environment

`docker-compose.testing.yml` sets all fake keys. The app container **never** has access to real Stripe/Printful/SendGrid keys. Run:

```bash
./scripts/docker-test.sh
```

---

## 6. Adding New Tests — Checklist

- [ ] Does the test trigger Stripe, Printful, or SendGrid? If yes → mock or fake.
- [ ] Use `Http::preventStrayRequests()` when testing code that might make HTTP calls.
- [ ] Never commit real API keys; `.env.testing` (if used) should have fake values only.
- [ ] For webhook tests: use fake payloads and `webhook_secret` null or test value.

---

## 7. Related Docs

- `docs/TEST_COVERAGE_STATUS_AND_PLAN.md` — Full test inventory and plan
- `docs/TESTING_SUMMARY.md` — Quick start and critical tests
- `docs/COVERAGE_WHAT_DONE_AND_NEXT.md` — What's done and safe next steps
