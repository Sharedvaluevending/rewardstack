<?php

namespace Tests\Unit;

use App\Models\Business;
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
class StripeServiceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_subscription_status_handles_error(): void
    {
        if (!class_exists(\Stripe\Subscription::class)) {
            $this->markTestSkipped('Stripe SDK not installed');
        }

        config(['services.stripe.secret' => 'sk_test_dummy']);

        $business = Business::factory()->create([
            'stripe_subscription_id' => 'sub_err',
            'subscription_tier' => 'starter',
        ]);

        $client = new class() implements \Stripe\HttpClient\ClientInterface {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                if ($method === 'get' && str_contains($absUrl, '/v1/subscriptions/sub_err')) {
                    return [json_encode(['error' => ['message' => 'error']]), 500, []];
                }

                throw new \Exception('Unexpected Stripe request: ' . $method . ' ' . $absUrl);
            }
        };

        \Stripe\ApiRequestor::setHttpClient($client);
        \Stripe\Stripe::setApiKey('sk_test_dummy');
        try {
            $service = app(StripeService::class);
            $status = $service->getSubscriptionStatus($business);

            $this->assertSame('error', $status['status']);
            $this->assertFalse($status['is_active']);
        } finally {
            \Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());
        }
    }
}

