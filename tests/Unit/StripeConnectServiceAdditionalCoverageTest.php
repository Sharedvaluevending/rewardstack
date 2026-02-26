<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StripeConnectServiceAdditionalCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));
        config(['stripe.connect.enabled' => true, 'services.stripe.secret' => 'sk_test']);
    }

    protected function makeStripeClient(array $overrides = []): \Stripe\StripeClient
    {
        if (!class_exists(\Stripe\StripeClient::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        $accounts = $overrides['accounts'] ?? new class {
            public array $created = [];
            public array $retrieved = [];

            public function create(array $params)
            {
                $this->created[] = $params;
                return (object) ['id' => 'acct_new_123'];
            }

            public function retrieve(string $id)
            {
                $this->retrieved[] = $id;
                return (object) [
                    'details_submitted' => true,
                    'payouts_enabled' => true,
                ];
            }

            public function createLoginLink(string $acct)
            {
                return (object) ['url' => 'https://stripe.test/login/' . $acct];
            }
        };

        $accountLinks = $overrides['accountLinks'] ?? new class {
            public function create(array $params)
            {
                return (object) ['url' => 'https://stripe.test/onboard/' . ($params['account'] ?? 'acct')];
            }
        };

        $transfers = $overrides['transfers'] ?? new class {
            public array $received = [];
            public function create(array $params, array $opts = [])
            {
                $this->received[] = compact('params', 'opts');
                return (object) ['id' => 'tr_123'];
            }
        };

        $balance = $overrides['balance'] ?? new class {
            public array $received = [];
            public function retrieve(array $params = [], array $opts = [])
            {
                $this->received[] = compact('params', 'opts');
                return (object) [
                    'available' => [(object) ['amount' => 12345]],
                    'pending' => [(object) ['amount' => 500]],
                ];
            }
        };

        return new class($accounts, $accountLinks, $transfers, $balance) extends \Stripe\StripeClient {
            public function __construct($accounts, $accountLinks, $transfers, $balance)
            {
                parent::__construct('sk_test_fake');
                $this->accounts = $accounts;
                $this->accountLinks = $accountLinks;
                $this->transfers = $transfers;
                $this->balance = $balance;
            }
        };
    }

    public function test_constructor_auto_creates_stripe_client_when_enabled(): void
    {
        if (!class_exists(\Stripe\StripeClient::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        $svc = new StripeConnectService();
        $this->assertTrue($svc->isEnabled());
    }

    public function test_create_connect_account_success_updates_user_and_uses_fallback_country(): void
    {
        config(['stripe.connect.default_country' => 'xx']); // invalid => should fallback to CA

        $accounts = new class {
            public array $created = [];
            public function create(array $params)
            {
                $this->created[] = $params;
                return (object) ['id' => 'acct_created_1'];
            }
        };

        $client = $this->makeStripeClient(['accounts' => $accounts]);

        $user = User::factory()->create([
            'role' => 'customer',
            'referral_code' => 'REF-XYZ',
            'stripe_connect_id' => null,
        ]);

        $svc = new StripeConnectService($client);
        $acct = $svc->createConnectAccount($user);

        $this->assertSame('acct_created_1', $acct);
        $user->refresh();
        $this->assertSame('acct_created_1', $user->stripe_connect_id);
        $this->assertSame('CA', $accounts->created[0]['country'] ?? null);
    }

    public function test_create_connect_account_handles_api_error_and_returns_null(): void
    {
        if (!class_exists(\Stripe\Exception\AuthenticationException::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        $accounts = new class {
            public function create(array $params)
            {
                throw new \Stripe\Exception\AuthenticationException('boom');
            }
        };

        $svc = new StripeConnectService($this->makeStripeClient(['accounts' => $accounts]));
        $user = User::factory()->create(['stripe_connect_id' => null]);

        $this->assertNull($svc->createConnectAccount($user));
    }

    public function test_links_return_null_when_missing_connect_id_and_handle_api_error(): void
    {
        if (!class_exists(\Stripe\Exception\AuthenticationException::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        $svc = new StripeConnectService($this->makeStripeClient());
        $userNoAcct = User::factory()->create(['stripe_connect_id' => null]);
        $this->assertNull($svc->createOnboardingLink($userNoAcct, 'https://return', 'https://refresh'));
        $this->assertNull($svc->createDashboardLink($userNoAcct));

        $accounts = new class {
            public function createLoginLink(string $acct)
            {
                throw new \Stripe\Exception\AuthenticationException('boom');
            }
        };
        $accountLinks = new class {
            public function create(array $params)
            {
                throw new \Stripe\Exception\AuthenticationException('boom');
            }
        };

        $svc2 = new StripeConnectService($this->makeStripeClient(['accounts' => $accounts, 'accountLinks' => $accountLinks]));
        $user = User::factory()->create(['stripe_connect_id' => 'acct_x']);
        $this->assertNull($svc2->createDashboardLink($user));
        $this->assertNull($svc2->createOnboardingLink($user, 'https://return', 'https://refresh'));
    }

    public function test_check_onboarding_status_updates_cached_flag_and_returns_false_on_error(): void
    {
        if (!class_exists(\Stripe\Exception\AuthenticationException::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        $accounts = new class {
            public function retrieve(string $id)
            {
                return (object) [
                    'details_submitted' => true,
                    'payouts_enabled' => true,
                ];
            }
        };
        $svc = new StripeConnectService($this->makeStripeClient(['accounts' => $accounts]));

        $user = User::factory()->create([
            'stripe_connect_id' => 'acct_ok',
            'stripe_connect_onboarded' => false,
        ]);
        $this->assertTrue($svc->checkOnboardingStatus($user));
        $user->refresh();
        $this->assertTrue((bool) $user->stripe_connect_onboarded);

        $accountsErr = new class {
            public function retrieve(string $id)
            {
                throw new \Stripe\Exception\AuthenticationException('boom');
            }
        };
        $svc2 = new StripeConnectService($this->makeStripeClient(['accounts' => $accountsErr]));
        $user2 = User::factory()->create(['stripe_connect_id' => 'acct_err']);
        $this->assertFalse($svc2->checkOnboardingStatus($user2));
    }

    public function test_create_payout_without_idempotency_key_sends_empty_options(): void
    {
        $transfers = new class {
            public array $received = [];
            public function create(array $params, array $opts = [])
            {
                $this->received[] = compact('params', 'opts');
                return (object) ['id' => 'tr_no_idem'];
            }
        };
        $svc = new StripeConnectService($this->makeStripeClient(['transfers' => $transfers]));

        $user = User::factory()->create([
            'stripe_connect_id' => 'acct_123',
            'stripe_connect_onboarded' => true,
            'payout_method' => 'stripe',
            'referral_code' => 'REF1',
        ]);

        $res = $svc->createPayout($user, 1.23, 'Desc', null);
        $this->assertTrue($res['success']);
        $this->assertSame('tr_no_idem', $res['transfer_id']);
        $this->assertSame([], $transfers->received[0]['opts']);
    }

    public function test_get_account_balance_success_and_null_cases(): void
    {
        if (!class_exists(\Stripe\Exception\AuthenticationException::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        $svc = new StripeConnectService($this->makeStripeClient());

        $userNoAcct = User::factory()->create(['stripe_connect_id' => null]);
        $this->assertNull($svc->getAccountBalance($userNoAcct));

        $user = User::factory()->create(['stripe_connect_id' => 'acct_bal']);
        $bal = $svc->getAccountBalance($user);
        $this->assertSame(123.45, $bal['available']);
        $this->assertSame(5, $bal['pending']);

        $balanceErr = new class {
            public function retrieve(array $params = [], array $opts = [])
            {
                throw new \Stripe\Exception\AuthenticationException('boom');
            }
        };
        $svc2 = new StripeConnectService($this->makeStripeClient(['balance' => $balanceErr]));
        $this->assertNull($svc2->getAccountBalance($user));
    }
}

