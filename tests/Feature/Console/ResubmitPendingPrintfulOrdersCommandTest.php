<?php

namespace Tests\Feature\Console;

use App\Jobs\SubmitPrintfulOrder;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResubmitPendingPrintfulOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_success_when_no_pending_orders(): void
    {
        $this->artisan('orders:resubmit-printful-pending')
            ->expectsOutputToContain('Dispatched 0 order(s)')
            ->assertExitCode(0);
    }

    public function test_command_dispatches_job_for_pending_paid_order(): void
    {
        Queue::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'printful_order_id' => null,
            'status' => 'pending',
            'printful_status' => null,
        ]);

        $this->artisan('orders:resubmit-printful-pending')
            ->expectsOutputToContain('Dispatched 1 order(s)')
            ->assertExitCode(0);

        Queue::assertPushed(SubmitPrintfulOrder::class, fn ($job) => $job->orderId === $order->id);
    }
}
