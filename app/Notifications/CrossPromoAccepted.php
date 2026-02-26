<?php

namespace App\Notifications;

use App\Models\CrossPromotion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CrossPromoAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CrossPromotion $crossPromo
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Partner Deal Chain Accepted: {$this->crossPromo->name}")
            ->line("Your partner has accepted the Partner Deal Chain: {$this->crossPromo->name}")
            ->action('View Partnership', url("/business/partnerships"))
            ->line('You can now create QR codes for this cross-promotion.');
    }
}
