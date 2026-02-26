<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchOrderFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $errorMessage = ''
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: Merch order fulfillment failed ({$this->order->order_number})")
            ->greeting('Order fulfillment issue')
            ->line("We were unable to submit your merch order to our fulfillment partner after multiple attempts.")
            ->line("Order number: {$this->order->order_number}")
            ->line("Business: {$this->order->business?->name}")
            ->line("The customer has already been charged. Please review this order and contact support if needed.")
            ->action('View your orders', url('/business/merch/orders'))
            ->line('Our team has been notified and will investigate.');
    }
}
