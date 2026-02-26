<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * This test aliases Stripe SDK classes, which can leak into other tests
 * in the same PHP process. Run it isolated to keep the full suite stable.
 *
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class StripeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_when_stripe_not_available(): void
    {
        config(['services.stripe.secret' => null]);

        $service = app(StripeService::class);
        $this->assertFalse($service->isAvailable());
    }

    public function test_create_checkout_session_throws_when_price_missing(): void
    {
        if (!class_exists(\Stripe\Checkout\Session::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        config(['services.stripe.secret' => 'sk_test_dummy']);

        $business = Business::factory()->create(['stripe_customer_id' => 'cus_123']);
        $plan = SubscriptionPlan::factory()->create([
            'stripe_monthly_price_id' => null,
        ]);

        $service = app(StripeService::class);

        $this->expectException(\Exception::class);
        $service->createCheckoutSession($business, $plan);
    }

    public function test_create_checkout_session_returns_null_when_customer_create_fails(): void
    {
        if (!class_exists(\Stripe\Checkout\Session::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        config(['services.stripe.secret' => 'sk_test_dummy']);

        $business = Business::factory()->create(['stripe_customer_id' => null]);
        $plan = SubscriptionPlan::factory()->create([
            'stripe_monthly_price_id' => 'price_test_123',
        ]);

        $client = new class() implements \Stripe\HttpClient\ClientInterface {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                if ($method === 'post' && str_contains($absUrl, '/v1/customers')) {
                    throw new \Exception('error');
                }

                throw new \Exception('Unexpected Stripe request: ' . $method . ' ' . $absUrl);
            }
        };

        \Stripe\ApiRequestor::setHttpClient($client);
        \Stripe\Stripe::setApiKey('sk_test_dummy');
        try {
            $service = app(StripeService::class);
            $session = $service->createCheckoutSession($business, $plan);
            $this->assertNull($session);
        } finally {
            \Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());
        }
    }
}

