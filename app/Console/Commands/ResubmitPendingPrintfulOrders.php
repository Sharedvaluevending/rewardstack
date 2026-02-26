<?php

namespace App\Console\Commands;

use App\Jobs\SubmitPrintfulOrder;
use App\Models\Order;
use Illuminate\Console\Command;

class ResubmitPendingPrintfulOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:resubmit-printful-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resubmit paid merch orders that have not been sent to Printful';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orders = Order::query()
            ->where('type', 'merch')
            ->where('payment_status', 'paid')
            ->whereNull('printful_order_id')
            ->whereIn('status', ['pending', 'processing'])
            ->where(function ($q) {
                $q->whereNull('printful_status')
                    ->orWhereIn('printful_status', ['submit_failed', 'pending']);
            })
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            SubmitPrintfulOrder::dispatch($order->id);
            $count++;
        }

        $this->info("Dispatched {$count} order(s) for Printful submission.");

        return Command::SUCCESS;
    }
}

