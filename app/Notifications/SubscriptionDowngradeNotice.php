<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionDowngradeNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Business $business,
        public string $oldTier,
        public string $newTier,
        public array $deactivated = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: Your plan has changed ({$this->oldTier} → {$this->newTier})")
            ->greeting("Plan changed for {$this->business->name}")
            ->line("Your subscription has been updated from **{$this->oldTier}** to **{$this->newTier}**.");

        if (!empty($this->deactivated)) {
            $mail->line('The following features were deactivated because they are not included in your new plan:');
            foreach ($this->deactivated as $item) {
                $mail->line("• {$item}");
            }
            $mail->line('You can re-activate these features by upgrading your plan.');
        }

        return $mail
            ->action('Manage Subscription', url('/business/billing'))
            ->line('If you have any questions, please contact support.');
    }
}
