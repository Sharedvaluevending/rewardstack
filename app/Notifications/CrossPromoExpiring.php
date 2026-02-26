<?php

namespace App\Notifications;

use App\Models\CrossPromotion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CrossPromoExpiring extends Notification
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
        $daysUntilExpiry = now()->diffInDays($this->crossPromo->expires_at, false);
        
        return (new MailMessage)
            ->subject("Partner Deal Chain Expiring Soon: {$this->crossPromo->name}")
            ->line("Your Partner Deal Chain '{$this->crossPromo->name}' will expire in {$daysUntilExpiry} days.")
            ->action('View Partnership', url("/business/partnerships"))
            ->line('Consider extending the expiration date or creating a new cross-promotion.');
    }
}
