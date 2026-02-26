<?php

namespace Tests\Feature;

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
class StripeServiceFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_session_bubbles_api_connection_exception(): void
    {
        $stripeSdkPath = base_path('vendor/stripe/stripe-php/init.php');
        if (!file_exists($stripeSdkPath)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        config(['services.stripe.secret' => 'sk_test_dummy']);

        // Avoid customer creation by setting an existing Stripe customer ID
        $business = Business::factory()->create([
            'stripe_customer_id' => 'cus_test_123',
        ]);

        $plan = SubscriptionPlan::factory()->create([
            'stripe_monthly_price_id' => 'price_test_123',
        ]);

        $client = new class() implements \Stripe\HttpClient\ClientInterface {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                if ($method === 'get' && str_contains($absUrl, '/v1/customers/cus_test_123')) {
                    return [json_encode(['id' => 'cus_test_123', 'object' => 'customer']), 200, []];
                }

                if ($method === 'post' && str_contains($absUrl, '/v1/checkout/sessions')) {
                    throw new \Stripe\Exception\ApiConnectionException('timeout');
                }

                throw new \Exception('Unexpected Stripe request: ' . $method . ' ' . $absUrl);
            }
        };

        \Stripe\ApiRequestor::setHttpClient($client);
        \Stripe\Stripe::setApiKey('sk_test_dummy');
        try {
            $service = app(StripeService::class);
            $this->expectException(\Stripe\Exception\ApiConnectionException::class);
            $service->createCheckoutSession($business, $plan);
        } finally {
            \Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());
        }
    }
}

