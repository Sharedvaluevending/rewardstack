<?php

namespace App\Notifications;

use App\Models\ReferralCommission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralCommissionEarned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ReferralCommission $commission)
    {
        // Ensure we have relations for email rendering
        $this->commission->loadMissing(['business', 'referrer']);
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        $businessName = $this->commission->business?->name ?? 'a business';
        $amount = number_format((float) $this->commission->commission_amount, 2);

        return [
            'message' => "You earned \${$amount} from {$businessName} (period: {$this->commission->subscription_period})",
            'action_url' => '/portal/referrals',
            'type' => 'referral_commission_earned',
            'icon' => '💰',
            'commission_id' => $this->commission->id,
            'business_id' => $this->commission->business_id,
            'amount' => $this->commission->commission_amount,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $businessName = $this->commission->business?->name ?? 'a business';
        $amount = number_format((float) $this->commission->commission_amount, 2);
        $period = $this->commission->subscription_period;

        return (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: You earned \${$amount} from {$businessName}")
            ->greeting("You just earned \${$amount}")
            ->line("Business: {$businessName}")
            ->line("Period: {$period}")
            ->line("Status: pending (will be approved before payout)")
            ->action('View referral earnings', url('/portal/referrals'))
            ->line('Thanks for growing Revenue QR!');
    }
}

