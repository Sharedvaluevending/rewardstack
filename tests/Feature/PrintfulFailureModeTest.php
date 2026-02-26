<?php

namespace Tests\Feature;

use App\Jobs\SubmitPrintfulOrder;
use App\Models\Order;
use App\Services\PrintfulService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrintfulFailureModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_printful_order_marks_submit_failed_on_500_response(): void
    {
        Http::fake([
            'api.printful.com/*' => Http::response([], 500),
        ]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => null,
            'printful_status' => null,
        ]);

        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));

        $order->refresh();

        $this->assertEquals('submit_failed', $order->printful_status);
        $this->assertNull($order->printful_order_id);
    }

    public function test_submit_printful_order_handles_timeout_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => null,
            'printful_status' => null,
        ]);

        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));

        $order->refresh();

        $this->assertEquals('submit_failed', $order->printful_status);
        $this->assertNull($order->printful_order_id);
    }

    public function test_cancelled_order_is_not_submitted_to_printful(): void
    {
        Http::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'cancelled',
            'printful_order_id' => null,
        ]);

        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));

        $order->refresh();

        Http::assertNothingSent();
        $this->assertNull($order->printful_order_id);
        $this->assertNull($order->printful_status);
    }

    public function test_already_submitted_order_is_not_resubmitted(): void
    {
        Http::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => 'pf_123',
            'printful_status' => 'pending',
        ]);

        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));

        Http::assertNothingSent();
        $order->refresh();
        $this->assertEquals('pf_123', $order->printful_order_id);
        $this->assertEquals('pending', $order->printful_status);
    }

    public function test_unpaid_order_is_not_submitted(): void
    {
        Http::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'pending',
            'status' => 'pending',
            'printful_order_id' => null,
        ]);

        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));

        Http::assertNothingSent();
        $order->refresh();
        $this->assertNull($order->printful_order_id);
        $this->assertNull($order->printful_status);
    }

    public function test_duplicate_submit_jobs_only_call_printful_once(): void
    {
        Http::fake([
            'api.printful.com/*' => Http::response([
                'result' => [
                    'id' => 'pf_dup_1',
                    'status' => 'pending',
                ],
            ], 200),
        ]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => null,
            'printful_status' => null,
        ]);

        // Two duplicate jobs processed sequentially (simulating dup queue)
        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));
        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));

        // PrintfulService makes two calls for a successful submit (create + confirm).
        // Duplicate job should not add extra calls beyond that baseline.
        Http::assertSentCount(2);

        $order->refresh();
        $this->assertEquals('pf_dup_1', $order->printful_order_id);
        $this->assertEquals('pending', $order->printful_status);
    }

    public function test_non_merch_order_is_not_submitted(): void
    {
        Http::fake();

        $order = Order::factory()->create([
            'type' => 'print_studio',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => null,
        ]);

        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));

        Http::assertNothingSent();
        $order->refresh();
        $this->assertNull($order->printful_order_id);
        $this->assertNull($order->printful_status);
    }

    public function test_submit_printful_order_sends_external_id(): void
    {
        Http::fake([
            'api.printful.com/orders' => Http::response([
                'result' => [
                    'id' => 'pf_ext_1',
                    'status' => 'pending',
                ],
            ], 200),
            'api.printful.com/orders/pf_ext_1/confirm' => Http::response([], 200),
        ]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'order_number' => 'ORD-EXT-1',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => null,
        ]);

        (new SubmitPrintfulOrder($order->id))->handle(app(PrintfulService::class));

        Http::assertSent(function ($request) use ($order) {
            if (!str_contains($request->url(), 'api.printful.com/orders')) {
                return false;
            }
            $data = $request->data();
            return ($data['external_id'] ?? null) === $order->order_number;
        });
    }
}

