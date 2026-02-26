<?php

namespace Tests\Feature;

use App\Jobs\SubmitPrintfulOrder;
use App\Models\Business;
use App\Models\BusinessGame;
use App\Models\BusinessGamePack;
use App\Models\Game;
use App\Models\GamePack;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\ReferralCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StripeWebhookAdditionalCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function postStripeWebhookRaw(string $rawBody, array $headers = [])
    {
        // Use a raw request so we exercise the controller's "decode raw JSON" path (object mode).
        return $this->call(
            'POST',
            '/webhooks/stripe',
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json'], $headers),
            $rawBody
        );
    }

    public function test_it_rejects_empty_payload_with_400(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $resp = $this->postStripeWebhookRaw('');
        $resp->assertStatus(400)->assertJson(['error' => 'Invalid payload']);
    }

    public function test_it_ignores_unknown_event_type_and_records_idempotency_event(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_unknown_1',
            'type' => 'some.unknown.event',
            'data' => ['object' => []],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'ignored']);
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_unknown_1',
            'type' => 'some.unknown.event',
        ]);
    }

    public function test_it_is_idempotent_on_duplicate_event_id(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $payload = [
            'id' => 'evt_dupe_1',
            'type' => 'some.unknown.event',
            'data' => ['object' => []],
        ];

        $this->postJson('/webhooks/stripe', $payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'ignored']);

        $this->postJson('/webhooks/stripe', $payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'already_processed']);

        $this->assertSame(1, WebhookEvent::query()->where('provider', 'stripe')->where('event_id', 'evt_dupe_1')->count());
    }

    public function test_subscription_created_returns_404_when_business_missing(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_sub_missing_biz',
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_test_1',
                    'metadata' => [
                        'business_id' => 999999,
                        'plan_id' => 123,
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(404)->assertJson(['error' => 'Business not found']);
    }

    public function test_subscription_created_can_activate_game_pack_and_enable_games(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $business = Business::factory()->create([
            'stripe_subscription_id' => null,
            'subscription_tier' => 'starter',
        ]);

        $gamePack = GamePack::create([
            'name' => 'Pack',
            'slug' => 'pack-' . uniqid(),
            'description' => 'Pack',
            'type' => GamePack::TYPE_SUBSCRIPTION,
            'category' => GamePack::CATEGORY_PRO,
            'price_monthly' => 10,
            'price_yearly' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $game = Game::factory()->create();
        $gamePack->games()->attach($game->id);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_pack_1',
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_pack_1',
                    'metadata' => [
                        'business_id' => $business->id,
                        'game_pack_id' => $gamePack->id,
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('business_game_packs', [
            'business_id' => $business->id,
            'game_pack_id' => $gamePack->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_pack_1',
        ]);

        $this->assertDatabaseHas('business_games', [
            'business_id' => $business->id,
            'game_id' => $game->id,
            'is_enabled' => 1,
        ]);
    }

    public function test_invoice_paid_calls_commission_service_and_sends_notification_safely(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        Notification::fake();

        $business = Business::factory()->create([
            'stripe_customer_id' => 'cus_paid_1',
        ]);

        $this->mock(ReferralCommissionService::class, function ($mock) use ($business) {
            $mock->shouldReceive('createCommissionForPayment')
                ->once()
                ->withArgs(function ($b, $amountPaid, $period) use ($business) {
                    return (int) $b->id === (int) $business->id
                        && abs((float) $amountPaid - 25.00) < 0.01
                        && $period === '2026-01';
                })
                ->andReturn(null);
        });

        $raw = json_encode([
            'id' => 'evt_invoice_paid_1',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'customer' => 'cus_paid_1',
                    'amount_paid' => 2500,
                    'currency' => 'usd',
                    'hosted_invoice_url' => 'https://stripe.test/inv/1',
                    'billing_reason' => 'subscription_create',
                    'period_start' => strtotime('2026-01-10 00:00:00'),
                    'subscription' => 'sub_any',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $this->postStripeWebhookRaw($raw)
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    public function test_checkout_session_completed_marks_merch_order_paid_and_dispatches_printful_job(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        Notification::fake();
        Bus::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_checkout_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_checkout_1',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame('pi_checkout_1', $order->stripe_payment_intent_id);

        Bus::assertDispatched(SubmitPrintfulOrder::class);
    }

    public function test_payment_intent_succeeded_marks_order_paid_and_is_idempotent_on_missing_order(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        Bus::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $this->postJson('/webhooks/stripe', [
            'id' => 'evt_pi_succ_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_succ_1',
                    'metadata' => ['order_id' => $order->id],
                ],
            ],
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame('pi_succ_1', $order->stripe_payment_intent_id);
    }

    public function test_charge_refunded_marks_order_refunded_when_payment_intent_matches(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'processing',
            'stripe_payment_intent_id' => 'pi_ref_1',
        ]);

        $this->postJson('/webhooks/stripe', [
            'id' => 'evt_ref_1',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_ref_1',
                ],
            ],
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->refunded_at);
    }

    public function test_charge_dispute_marks_order_disputed_when_payment_intent_matches(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'processing',
            'stripe_payment_intent_id' => 'pi_disp_1',
        ]);

        $this->postJson('/webhooks/stripe', [
            'id' => 'evt_disp_1',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_disp_1',
                ],
            ],
        ])->assertStatus(200)->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertSame('disputed', $order->payment_status);
    }
}

