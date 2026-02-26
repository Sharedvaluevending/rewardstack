<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchOrderNeedsAttention extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string,mixed> $details
     */
    public function __construct(public Order $order, public string $reason, public array $details = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = trim($this->reason) !== '' ? $this->reason : 'An issue occurred while processing your order.';

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: Action needed for merch order ({$this->order->order_number})")
            ->greeting('Action needed')
            ->line("Your merch order for {$this->order->business?->name} needs attention.")
            ->line("Order number: {$this->order->order_number}")
            ->line($reason)
            ->action('View your order', url('/business/merch/orders'))
            ->line('If you need help, just reply to this email.');

        return $mail;
    }
}

