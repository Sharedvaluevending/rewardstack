<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\MerchOrderNeedsAttention;
use App\Notifications\MerchOrderProcessing;
use App\Notifications\MerchOrderShipped;
use App\Services\PrintfulService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPrintfulOrders extends Command
{
    protected $signature = 'printful:sync-orders
                            {--order= : Sync a specific order by order_number}';

    protected $description = 'Poll Printful for order status updates that may have been missed by webhooks';

    public function handle(PrintfulService $printful): int
    {
        $specificOrder = $this->option('order');

        $query = Order::whereNotNull('printful_order_id')
            ->whereNotIn('status', ['shipped', 'delivered', 'cancelled']);

        if ($specificOrder) {
            $query->where('order_number', $specificOrder);
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->info('No pending Printful orders to sync.');
            return Command::SUCCESS;
        }

        $this->info("Syncing {$orders->count()} order(s) with Printful...");
        $synced = 0;

        foreach ($orders as $order) {
            try {
                $pfData = $printful->getOrderStatus((string) $order->printful_order_id);

                if (empty($pfData)) {
                    $this->warn("  {$order->order_number}: no data from Printful");
                    continue;
                }

                $pfStatus = $pfData['status'] ?? null;
                $shipment = $pfData['shipments'][0] ?? [];

                $statusMap = [
                    'draft'     => 'pending',
                    'pending'   => 'pending',
                    'failed'    => 'cancelled',
                    'canceled'  => 'cancelled',
                    'inprocess' => 'processing',
                    'onhold'    => 'processing',
                    'partial'   => 'processing',
                    'fulfilled' => 'shipped',
                ];

                $newStatus = $statusMap[$pfStatus] ?? $order->status;

                if ($newStatus === $order->status && $pfStatus === $order->printful_status) {
                    $this->line("  {$order->order_number}: already in sync ({$order->status})");
                    continue;
                }

                $updates = [
                    'printful_status' => $pfStatus,
                    'status' => $newStatus,
                ];

                if ($newStatus === 'shipped' && !$order->shipped_at) {
                    $updates['tracking_number'] = $shipment['tracking_number'] ?? $order->tracking_number;
                    $updates['tracking_url'] = $shipment['tracking_url'] ?? $order->tracking_url;
                    $updates['shipped_at'] = now();
                }

                $previousStatus = $order->status;
                $order->update($updates);

                $this->notifyStatusChange($order, $previousStatus, $newStatus, $pfStatus);

                $this->info("  {$order->order_number}: {$order->getOriginal('status')} -> {$newStatus}");
                $synced++;
            } catch (\Throwable $e) {
                $this->error("  {$order->order_number}: error - {$e->getMessage()}");
                Log::error('Printful sync failed for order', [
                    'order' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. {$synced} order(s) updated.");
        Log::info("printful:sync-orders completed", ['total' => $orders->count(), 'synced' => $synced]);

        return Command::SUCCESS;
    }

    protected function notifyStatusChange(Order $order, string $previousStatus, string $newStatus, ?string $pfStatus): void
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
                $reason = $pfStatus === 'failed'
                    ? 'Printful fulfillment failed.'
                    : 'Printful fulfillment was canceled.';
                $order->business?->owner?->notify(new MerchOrderNeedsAttention($order, $reason));
            }
        } catch (\Throwable $e) {
            Log::warning('Sync: failed to send status notification', [
                'order' => $order->order_number,
                'transition' => "{$previousStatus} -> {$newStatus}",
                'error' => $e->getMessage(),
            ]);
        }
    }
}
