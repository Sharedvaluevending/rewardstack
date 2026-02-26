<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeConnectServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeStripeClient(array $overrides = [])
    {
        if (!class_exists(\Stripe\StripeClient::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        $transfers = $overrides['transfers'] ?? new class {
            public array $received = [];
            public function create($params, $opts = [])
            {
                $this->received[] = compact('params', 'opts');
                return (object) ['id' => 'tr_123'];
            }
        };

        $accounts = $overrides['accounts'] ?? new class {
            public function createLoginLink($acct)
            {
                return (object) ['url' => 'https://stripe.test/login/' . $acct];
            }
        };

        $accountLinks = $overrides['accountLinks'] ?? new class {
            public function create($params)
            {
                return (object) ['url' => 'https://stripe.test/onboard/' . ($params['account'] ?? 'acct')];
            }
        };

        return new class($transfers, $accounts, $accountLinks) extends \Stripe\StripeClient {
            public function __construct($transfers, $accounts, $accountLinks)
            {
                parent::__construct('sk_test_fake');
                $this->transfers = $transfers;
                $this->accounts = $accounts;
                $this->accountLinks = $accountLinks;
            }
        };
    }

    public function test_create_payout_returns_null_when_not_onboarded(): void
    {
        config(['stripe.connect.enabled' => true, 'services.stripe.secret' => 'sk_test']);

        $user = User::factory()->create([
            'stripe_connect_id' => 'acct_123',
            'stripe_connect_onboarded' => false,
            'payout_method' => 'stripe',
        ]);

        $service = new StripeConnectService($this->makeStripeClient());
        $res = $service->createPayout($user, 10.0, 'Desc', 'key1');

        $this->assertNull($res);
    }

    public function test_create_payout_success_with_idempotency(): void
    {
        config(['stripe.connect.enabled' => true, 'services.stripe.secret' => 'sk_test']);

        $transfers = new class {
            public array $received = [];
            public function create($params, $opts = [])
            {
                $this->received[] = compact('params', 'opts');
                return (object) ['id' => 'tr_success'];
            }
        };

        $client = $this->makeStripeClient(['transfers' => $transfers]);

        $user = User::factory()->create([
            'stripe_connect_id' => 'acct_123',
            'stripe_connect_onboarded' => true,
            'payout_method' => 'stripe',
            'referral_code' => 'REF1',
        ]);

        $service = new StripeConnectService($client);
        $res = $service->createPayout($user, 12.34, 'Referral', 'idem-1');

        $this->assertTrue($res['success']);
        $this->assertEquals('tr_success', $res['transfer_id']);
        $this->assertEquals('idem-1', $transfers->received[0]['opts']['idempotency_key']);
        $this->assertEquals(1234, $transfers->received[0]['params']['amount']);
    }

    public function test_create_payout_handles_api_error(): void
    {
        if (!class_exists(\Stripe\Exception\ApiErrorException::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        config(['stripe.connect.enabled' => true, 'services.stripe.secret' => 'sk_test']);

        $transfers = new class {
            public function create($params, $opts = [])
            {
                throw new \Stripe\Exception\AuthenticationException('boom');
            }
        };

        $client = $this->makeStripeClient(['transfers' => $transfers]);

        $user = User::factory()->create([
            'stripe_connect_id' => 'acct_123',
            'stripe_connect_onboarded' => true,
            'payout_method' => 'stripe',
        ]);

        $service = new StripeConnectService($client);
        $res = $service->createPayout($user, 5.0, 'Referral', 'idem-err');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('boom', $res['error']);
    }

    public function test_create_dashboard_and_onboarding_links(): void
    {
        config(['stripe.connect.enabled' => true, 'services.stripe.secret' => 'sk_test']);

        $client = $this->makeStripeClient();
        $user = User::factory()->create([
            'stripe_connect_id' => 'acct_abc',
            'stripe_connect_onboarded' => true,
            'payout_method' => 'stripe',
        ]);

        $service = new StripeConnectService($client);

        $dashboard = $service->createDashboardLink($user);
        $this->assertStringContainsString('login/acct_abc', $dashboard);

        $onboard = $service->createOnboardingLink($user, 'https://return', 'https://refresh');
        $this->assertStringContainsString('onboard/acct_abc', $onboard);
    }
}

