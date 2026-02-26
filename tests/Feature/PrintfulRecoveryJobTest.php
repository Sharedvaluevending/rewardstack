<?php

namespace Tests\Feature;

use App\Jobs\SubmitPrintfulOrder;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrintfulRecoveryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_command_dispatches_submit_job_for_paid_pending_orders(): void
    {
        Queue::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => null,
            'printful_status' => 'submit_failed',
        ]);

        $this->artisan('orders:resubmit-printful-pending')
            ->assertExitCode(0);

        Queue::assertPushed(SubmitPrintfulOrder::class, function ($job) use ($order) {
            return $job->orderId === $order->id;
        });
    }

    public function test_recovery_command_skips_already_submitted_orders(): void
    {
        Queue::fake();

        Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => 'pf_exists_1',
            'printful_status' => 'pending',
        ]);

        $this->artisan('orders:resubmit-printful-pending')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }
}

