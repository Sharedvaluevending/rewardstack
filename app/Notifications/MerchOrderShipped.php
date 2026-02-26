<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchOrderShipped extends Notification implements ShouldQueue
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
        $trackingNumber = (string) ($this->order->tracking_number ?? '');
        $trackingUrl = (string) ($this->order->tracking_url ?? '');

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: Merch order shipped ({$this->order->order_number})")
            ->greeting('Your order is on the way')
            ->line("Good news — your merch order for {$this->order->business?->name} has shipped.")
            ->line("Order number: {$this->order->order_number}");

        if ($trackingNumber !== '') {
            $mail->line("Tracking number: {$trackingNumber}");
        }

        if ($trackingUrl !== '') {
            $mail->action('Track your package', $trackingUrl);
        } else {
            $mail->action('View your order', url('/business/merch/orders'));
        }

        return $mail->line('Thanks for using Revenue QR.');
    }
}

