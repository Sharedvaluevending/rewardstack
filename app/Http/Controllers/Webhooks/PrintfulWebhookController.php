<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Notifications\MerchOrderNeedsAttention;
use App\Notifications\MerchOrderProcessing;
use App\Notifications\MerchOrderShipped;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PrintfulWebhookController extends Controller
{
    /**
     * Handle incoming Printful webhooks
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        $type = $payload['type'] ?? null;

        // Avoid logging full payload to prevent accidental PII leakage
        $data = $payload['data'] ?? [];
        $orderId = $data['order']['id'] ?? null;
        $externalId = $data['order']['external_id'] ?? null;

        Log::info('Printful webhook received', [
            'type' => $type,
            'order_id' => $orderId,
            'external_id' => $externalId,
        ]);

        // Idempotency: dedupe by provider + event_id before any processing
        $eventId = $payload['event_id']
            ?? $payload['id']
            ?? ($payload['data']['event_id'] ?? null)
            ?? ($payload['data']['id'] ?? null);

        // Fall back to a stable hash of canonical fields (avoid volatile delivery metadata)
        if (!$eventId) {
            $canonicalPieces = [
                $type ?? 'unknown',
                data_get($payload, 'data.order.id', 'no_printful_id'),
                data_get($payload, 'data.order.external_id', 'no_external_id'),
                (string) (data_get($payload, 'data.created') ?? data_get($payload, 'created') ?? 'no_created_at'),
            ];
            $eventId = sha1(implode('|', $canonicalPieces));
        }

        if ($eventId) {
            try {
                WebhookEvent::create([
                    'provider' => 'printful',
                    'event_id' => (string) $eventId,
                    'type' => $type,
                    'processed_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '23000') {
                    Log::info('Printful webhook already processed', [
                        'event_id' => $eventId,
                        'type' => $type,
                    ]);
                    return response()->json(['status' => 'already_processed'], 200);
                }
                throw $e;
            }
        }

        switch ($type) {
            case 'package_shipped':
                return $this->handlePackageShipped($payload);

            case 'order_created':
                return $this->handleOrderCreated($payload);

            case 'order_updated':
                return $this->handleOrderUpdated($payload);

            case 'order_failed':
                return $this->handleOrderFailed($payload);

            case 'order_canceled':
                return $this->handleOrderCanceled($payload);

            case 'order_put_hold':
                return $this->handleOrderPutHold($payload);

            case 'product_synced':
            case 'product_updated':
                return $this->handleProductUpdated($payload);

            case 'stock_updated':
                return $this->handleStockUpdated($payload);

            default:
                Log::info('Unhandled Printful webhook type: ' . $type);
                return response()->json(['status' => 'ignored']);
        }
    }

    /**
     * Handle package shipped event
     */
    protected function handlePackageShipped(array $payload): \Illuminate\Http\JsonResponse
    {
        $data = $payload['data'] ?? [];
        $printfulOrderId = $data['order']['id'] ?? null;
        $shipment = $data['shipment'] ?? [];

        if (!$printfulOrderId) {
            return response()->json(['error' => 'Missing order ID'], 400);
        }

        $order = Order::where('printful_order_id', $printfulOrderId)->first();

        if (!$order) {
            Log::warning("Order not found for Printful ID: {$printfulOrderId}");
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Update order with shipping info
        $order->update([
            'status' => 'shipped',
            'printful_status' => 'shipped',
            'tracking_number' => $shipment['tracking_number'] ?? null,
            'tracking_url' => $shipment['tracking_url'] ?? null,
            'shipped_at' => now(),
        ]);

        // Send shipping notification email to business owner (async)
        try {
            $order->business?->owner?->notify(new MerchOrderShipped($order));
        } catch (\Throwable $e) {
            Log::warning('Failed to send merch shipped email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("Order {$order->order_number} marked as shipped");

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle order created event
     */
    protected function handleOrderCreated(array $payload): \Illuminate\Http\JsonResponse
    {
        $data = $payload['data'] ?? [];
        $printfulOrderId = $data['order']['id'] ?? null;
        $externalId = $data['order']['external_id'] ?? null;

        if ($externalId) {
            // Find by our order number
            $order = Order::where('order_number', $externalId)->first();

            if ($order && !$order->printful_order_id) {
                $order->update([
                    'printful_order_id' => $printfulOrderId,
                    'printful_status' => $data['order']['status'] ?? 'pending',
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle order updated event
     */
    protected function handleOrderUpdated(array $payload): \Illuminate\Http\JsonResponse
    {
        $data = $payload['data'] ?? [];
        $printfulOrderId = $data['order']['id'] ?? null;
        $printfulStatus = $data['order']['status'] ?? null;

        if (!$printfulOrderId) {
            return response()->json(['error' => 'Missing order ID'], 400);
        }

        $order = Order::where('printful_order_id', $printfulOrderId)->first();

        if ($order) {
            $statusMapping = [
                'draft' => 'pending',
                'pending' => 'pending',
                'failed' => 'cancelled',
                'canceled' => 'cancelled',
                'inprocess' => 'processing',
                'onhold' => 'processing',
                'partial' => 'processing',
                'fulfilled' => 'shipped',
            ];

            $previousStatus = $order->status;
            $newStatus = $statusMapping[$printfulStatus] ?? $order->status;

            $updates = [
                'printful_status' => $printfulStatus,
                'status' => $newStatus,
            ];

            if ($newStatus === 'shipped' && !$order->shipped_at) {
                $shipment = $data['shipment'] ?? ($data['order']['shipments'][0] ?? []);
                $updates['tracking_number'] = $shipment['tracking_number'] ?? $order->tracking_number;
                $updates['tracking_url'] = $shipment['tracking_url'] ?? $order->tracking_url;
                $updates['shipped_at'] = now();
            }

            $order->update($updates);

            $this->notifyStatusChange($order, $previousStatus, $newStatus, $printfulStatus);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Send appropriate notification when order status changes.
     */
    protected function notifyStatusChange(Order $order, string $previousStatus, string $newStatus, ?string $printfulStatus): void
    {
        if ($previousStatus === $newStatus) {
            return;
        }

        try {
            if ($newStatus === 'processing' && $previousStatus !== 'processing') {
                $order->business?->owner?->notify(new MerchOrderProcessing($order));
            } elseif ($newStatus === 'shipped' && $previousStatus !== 'shipped') {
                $order->business?->owner?->notify(new MerchOrderShipped($order));
            } elseif ($newStatus === 'cancelled' && $previousStatus !== 'cancelled') {
                $reason = $printfulStatus === 'failed'
                    ? 'Your Printful fulfillment has failed.'
                    : 'Your Printful fulfillment was canceled.';
                $order->business?->owner?->notify(new MerchOrderNeedsAttention($order, $reason));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send order status notification', [
                'order_id' => $order->id,
                'transition' => "{$previousStatus} -> {$newStatus}",
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle order failed event
     */
    protected function handleOrderFailed(array $payload): \Illuminate\Http\JsonResponse
    {
        $data = $payload['data'] ?? [];
        $printfulOrderId = $data['order']['id'] ?? null;
        $reason = $data['reason'] ?? 'Unknown error';

        if (!$printfulOrderId) {
            return response()->json(['error' => 'Missing order ID'], 400);
        }

        $order = Order::where('printful_order_id', $printfulOrderId)->first();

        if ($order) {
            $order->update([
                'printful_status' => 'failed',
                'status' => 'cancelled',
            ]);

            Log::error("Printful order failed: {$order->order_number}", [
                'reason' => $reason,
            ]);

            // Notify business of failed order (async)
            try {
                $order->business?->owner?->notify(new MerchOrderNeedsAttention($order, (string) $reason));
            } catch (\Throwable $e) {
                Log::warning('Failed to send merch needs-attention email (failed)', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle order canceled event
     */
    protected function handleOrderCanceled(array $payload): \Illuminate\Http\JsonResponse
    {
        $data = $payload['data'] ?? [];
        $printfulOrderId = $data['order']['id'] ?? null;

        if (!$printfulOrderId) {
            return response()->json(['error' => 'Missing order ID'], 400);
        }

        $order = Order::where('printful_order_id', $printfulOrderId)->first();

        if ($order) {
            $order->update([
                'printful_status' => 'canceled',
                'status' => 'cancelled',
            ]);

            try {
                $order->business?->owner?->notify(new MerchOrderNeedsAttention($order, 'Your Printful fulfillment was canceled.'));
            } catch (\Throwable $e) {
                Log::warning('Failed to send merch needs-attention email (canceled)', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle order put on hold event
     */
    protected function handleOrderPutHold(array $payload): \Illuminate\Http\JsonResponse
    {
        $data = $payload['data'] ?? [];
        $printfulOrderId = $data['order']['id'] ?? null;
        $reason = $data['reason'] ?? 'Unknown';

        if (!$printfulOrderId) {
            return response()->json(['error' => 'Missing order ID'], 400);
        }

        $order = Order::where('printful_order_id', $printfulOrderId)->first();

        if ($order) {
            $order->update([
                'printful_status' => 'onhold',
            ]);

            Log::warning("Order {$order->order_number} put on hold: {$reason}");
            
            // Notify business about the hold (async)
            try {
                $order->business?->owner?->notify(new MerchOrderNeedsAttention($order, "Your Printful fulfillment is on hold: {$reason}"));
            } catch (\Throwable $e) {
                Log::warning('Failed to send merch needs-attention email (onhold)', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle product updated event
     */
    protected function handleProductUpdated(array $payload): \Illuminate\Http\JsonResponse
    {
        // Could trigger a sync of product data if needed
        Log::info('Product sync event received', $payload['data'] ?? []);
        return response()->json(['status' => 'success']);
    }

    /**
     * Handle stock updated event
     */
    protected function handleStockUpdated(array $payload): \Illuminate\Http\JsonResponse
    {
        // Could update local stock info if tracking inventory
        Log::info('Stock update event received', $payload['data'] ?? []);
        return response()->json(['status' => 'success']);
    }
}
