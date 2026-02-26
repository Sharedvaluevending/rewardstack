<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchOrderProcessing extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: Merch order in production ({$this->order->order_number})")
            ->greeting('Your order is being made!')
            ->line("Great news — your merch order for {$this->order->business?->name} is now in production.")
            ->line("Order number: {$this->order->order_number}")
            ->line("We'll email you again once it ships with tracking info.")
            ->action('View your order', url('/business/merch/orders'))
            ->line('Thanks for using Revenue QR.');
    }
}
