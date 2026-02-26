<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralBusinessUpgraded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Referral $referral,
        public Business $business,
        public string $oldTier,
        public string $newTier
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
            ->subject("Revenue QR: {$businessName} upgraded their plan!")
            ->greeting("Great news! 🎉")
            ->line("**{$businessName}** upgraded from **{$this->oldTier}** to **{$this->newTier}**!")
            ->line("Since your commission is based on what they pay, your future earnings from this referral will increase.")
            ->line("You earn **{$this->referral->commission_rate}%** of their subscription — the higher their plan, the more you make!")
            ->action('View your referrals', url('/portal/referrals'))
            ->line('Thanks for growing Revenue QR!');
    }

    public function toArray(object $notifiable): array
    {
        $businessName = $this->business->name ?? 'A business';

        return [
            'message' => "{$businessName} upgraded from {$this->oldTier} to {$this->newTier}! Your future commissions will be higher.",
            'action_url' => '/portal/referrals',
            'type' => 'referral_business_upgraded',
            'icon' => '🎉',
            'business_id' => $this->business->id,
            'referral_id' => $this->referral->id,
            'old_tier' => $this->oldTier,
            'new_tier' => $this->newTier,
        ];
    }
}
