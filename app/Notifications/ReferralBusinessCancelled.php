<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralBusinessCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Referral $referral,
        public Business $business
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $businessName = $this->business->name ?? 'a business';

        return (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: {$businessName} cancelled their subscription")
            ->greeting("Referral Update")
            ->line("**{$businessName}** has cancelled their subscription.")
            ->line("You will no longer earn recurring commissions from this business.")
            ->line("Any pending or approved commissions you've already earned will still be paid out.")
            ->action('View your referrals', url('/portal/referrals'))
            ->line('Keep sharing your referral link to grow your income!');
    }

    public function toArray(object $notifiable): array
    {
        $businessName = $this->business->name ?? 'A business';

        return [
            'message' => "{$businessName} cancelled their subscription. No future commissions will be generated from this referral.",
            'action_url' => '/portal/referrals',
            'type' => 'referral_business_cancelled',
            'icon' => '⚠️',
            'business_id' => $this->business->id,
            'referral_id' => $this->referral->id,
        ];
    }
}
