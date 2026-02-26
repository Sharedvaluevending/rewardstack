<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PrintfulService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitPrintfulOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(public int $orderId)
    {
    }

    public function handle(PrintfulService $printful): void
    {
        $order = Order::with(['items.product'])->find($this->orderId);
        if (!$order) {
            return;
        }

        if ($order->type !== 'merch') {
            return;
        }

        // Only submit paid orders
        if ($order->payment_status !== 'paid') {
            return;
        }

        // Idempotency: don't create duplicates
        if (!empty($order->printful_order_id)) {
            return;
        }

        // Basic safety: avoid submitting cancelled orders
        if (in_array($order->status, ['cancelled'], true)) {
            return;
        }

        try {
            $result = $printful->createOrder($order);

            $printfulOrderId = $result['id'] ?? null;
            if ($printfulOrderId) {
                // Attempt to confirm/approve the order for fulfillment.
                // If this fails, Printful will typically keep the order in draft/pending state.
                $confirmed = $printful->confirmOrder((string) $printfulOrderId);
                if (!$confirmed) {
                    Log::warning('Printful order created but not confirmed', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'printful_order_id' => $printfulOrderId,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('SubmitPrintfulOrder failed (attempt ' . $this->attempts() . "/{$this->tries})", [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            // Re-throw so Laravel retries with backoff. The failed() method
            // handles permanent failure after all retries are exhausted.
            throw $e;
        }
    }

    /**
     * Called by Laravel when all retry attempts have been exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $order = Order::find($this->orderId);
        if (!$order) {
            return;
        }

        $order->update([
            'printful_status' => 'submit_failed',
            'status' => 'failed',
        ]);

        Log::critical('SubmitPrintfulOrder permanently failed after all retries', [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);

        // Notify the business owner so they can take action
        try {
            $order->loadMissing('business.owner');
            $owner = $order->business?->owner;
            if ($owner) {
                $owner->notify(new \App\Notifications\MerchOrderFailed($order, $exception->getMessage()));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify business owner about merch order failure', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

