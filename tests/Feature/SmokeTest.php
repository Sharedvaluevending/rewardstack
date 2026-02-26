<?php

namespace Tests\Feature;

use App\Jobs\SubmitPrintfulOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Smoke tests: critical paths that must work without hitting Stripe/Printful/SendGrid.
 * All routes are read-only or use fake keys; no live API calls.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_boots_and_db_connects(): void
    {
        $row = DB::select('select 1 as one');
        $this->assertEquals(1, $row[0]->one ?? null);
    }

    public function test_resubmit_command_no_orders_is_noop(): void
    {
        Queue::fake();

        $this->artisan('orders:resubmit-printful-pending')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_printful_webhook_basic_flow(): void
    {
        config(['services.printful.webhook_token' => null]);

        $payload = [
            'id' => 'evt_smoke_pf_1',
            'type' => 'stock_updated',
            'data' => [
                'variant' => [
                    'id' => 'smoke-1',
                    'stock' => 10,
                ],
            ],
        ];

        $resp = $this->postJson('/webhooks/printful', $payload);
        $resp->assertStatus(200)->assertJson(['status' => 'success']);
    }

    public function test_stripe_webhook_signature_required_when_secret_set(): void
    {
        config(['services.stripe.webhook_secret' => 'smoke_secret']);

        $resp = $this->postJson('/webhooks/stripe', [
            'type' => 'customer.subscription.created',
            'data' => ['object' => []],
        ]);

        $resp->assertStatus(400)->assertJson(['error' => 'Invalid signature']);
    }

    public function test_security_headers_allow_avery_form_posts(): void
    {
        config(['avery.merge_direct_url' => 'https://services.print.avery.com/dpp/public/v3/dpo/merge/direct']);

        $resp = $this->get('/health');

        $resp->assertOk();
        $csp = (string) $resp->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("form-action 'self' https://services.print.avery.com", $csp);
    }

    /** @dataProvider publicRoutesProvider */
    public function test_public_routes_return_ok_or_redirect(string $path): void
    {
        $resp = $this->get($path);
        $this->assertContains($resp->status(), [200, 302], "Expected 200 or 302 for {$path}");
    }

    /** @return array<string, array{string}> */
    public static function publicRoutesProvider(): array
    {
        return [
            'home' => ['/'],
            'features' => ['/features'],
            'pricing' => ['/pricing'],
            'demo' => ['/demo'],
            'privacy' => ['/privacy'],
            'terms' => ['/terms'],
            'health' => ['/health'],
            'login' => ['/login'],
            'register' => ['/register'],
        ];
    }

    public function test_health_returns_json_with_ok(): void
    {
        $resp = $this->getJson('/health');
        $resp->assertOk()->assertJson(['ok' => true]);
    }

    public function test_login_page_loads(): void
    {
        $resp = $this->get('/login');
        $resp->assertOk();
    }

    public function test_register_page_loads(): void
    {
        $resp = $this->get('/register');
        $resp->assertOk();
    }
}

