<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These tests alias-mock Stripe SDK classes, which can leak into other tests
 * in the same PHP process. Run them isolated to keep the full suite stable.
 *
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class StripeServiceHappyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_checkout_session_returns_null_when_customer_missing_and_creation_fails(): void
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

    public function test_create_checkout_session_success_path_returns_session(): void
    {
        if (!class_exists(\Stripe\Checkout\Session::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        config(['services.stripe.secret' => 'sk_test_dummy']);

        $business = Business::factory()->create(['stripe_customer_id' => 'cus_hp_123']);
        $plan = SubscriptionPlan::factory()->create([
            'stripe_monthly_price_id' => 'price_hp_123',
        ]);

        $client = new class() implements \Stripe\HttpClient\ClientInterface {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                if ($method === 'get' && str_contains($absUrl, '/v1/customers/cus_hp_123')) {
                    return [json_encode(['id' => 'cus_hp_123', 'object' => 'customer']), 200, []];
                }

                if ($method === 'post' && str_contains($absUrl, '/v1/checkout/sessions')) {
                    return [json_encode(['id' => 'cs_hp_123', 'object' => 'checkout.session', 'url' => 'https://stripe.test/checkout']), 200, []];
                }

                throw new \Exception('Unexpected Stripe request: ' . $method . ' ' . $absUrl);
            }
        };

        \Stripe\ApiRequestor::setHttpClient($client);
        \Stripe\Stripe::setApiKey('sk_test_dummy');
        try {
            $service = app(StripeService::class);
            $session = $service->createCheckoutSession($business, $plan);
            $this->assertEquals('cs_hp_123', $session->id);
        } finally {
            \Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());
        }
    }

    public function test_billing_portal_session_returns_session(): void
    {
        if (!class_exists(\Stripe\BillingPortal\Session::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        config(['services.stripe.secret' => 'sk_test_dummy']);

        $business = Business::factory()->create(['stripe_customer_id' => 'cus_portal_123']);

        $client = new class() implements \Stripe\HttpClient\ClientInterface {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                if ($method === 'get' && str_contains($absUrl, '/v1/customers/cus_portal_123')) {
                    return [json_encode(['id' => 'cus_portal_123', 'object' => 'customer']), 200, []];
                }

                if ($method === 'post' && str_contains($absUrl, '/v1/billing_portal/sessions')) {
                    return [json_encode(['id' => 'bps_123', 'object' => 'billing_portal.session', 'url' => 'https://stripe.test/portal']), 200, []];
                }

                throw new \Exception('Unexpected Stripe request: ' . $method . ' ' . $absUrl);
            }
        };

        \Stripe\ApiRequestor::setHttpClient($client);
        \Stripe\Stripe::setApiKey('sk_test_dummy');
        try {
            $service = app(StripeService::class);
            $session = $service->createPortalSession($business);
            $this->assertEquals('bps_123', $session->id);
        } finally {
            \Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());
        }
    }
}

