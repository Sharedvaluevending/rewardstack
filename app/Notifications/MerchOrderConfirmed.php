<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchOrderConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) ($this->order->total ?? 0), 2);
        $currency = strtoupper((string) ($this->order->shipping_country ?? 'CA')) === 'US' ? 'USD' : 'CAD';

        return (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: Merch order confirmed ({$this->order->order_number})")
            ->greeting('Order confirmed')
            ->line("We’ve received your merch order for {$this->order->business?->name}.")
            ->line("Order number: {$this->order->order_number}")
            ->line("Total: {$currency} {$amount}")
            ->action('View your order', url('/business/merch/orders'))
            ->line('We’ll email you again when your order ships with tracking.');
    }
}

