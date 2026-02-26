<?php

namespace App\Notifications;

use App\Models\ReferralCommission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralCommissionReversed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ReferralCommission $commission,
        public string $reason
    ) {
        $this->commission->loadMissing(['business']);
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $businessName = $this->commission->business?->name ?? 'a business';
        $amount = number_format((float) $this->commission->commission_amount, 2);

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'));

        return match ($this->reason) {
            'refund' => $mail
                ->subject("Revenue QR: Commission reversed — \${$amount}")
                ->greeting('Commission Reversed')
                ->line("The subscription payment from **{$businessName}** for period {$this->commission->subscription_period} was refunded.")
                ->line("Your \${$amount} commission for this payment has been cancelled.")
                ->line("This does not affect commissions from other billing periods.")
                ->action('View your referrals', url('/portal/referrals')),

            'dispute' => $mail
                ->subject("Revenue QR: Commission on hold — \${$amount}")
                ->greeting('Commission On Hold')
                ->line("A payment dispute has been opened for **{$businessName}** (period: {$this->commission->subscription_period}).")
                ->line("Your \${$amount} commission is on hold until the dispute is resolved.")
                ->line("If the dispute is resolved in the business's favor, your commission will be restored.")
                ->action('View your referrals', url('/portal/referrals')),

            'dispute_won' => $mail
                ->subject("Revenue QR: Commission restored — \${$amount}")
                ->greeting('Commission Restored')
                ->line("The payment dispute for **{$businessName}** has been resolved in the business's favor.")
                ->line("Your \${$amount} commission has been restored and is back to pending.")
                ->action('View your referrals', url('/portal/referrals')),

            'dispute_lost' => $mail
                ->subject("Revenue QR: Commission cancelled — \${$amount}")
                ->greeting('Commission Cancelled')
                ->line("The payment dispute for **{$businessName}** (period: {$this->commission->subscription_period}) was lost.")
                ->line("Your \${$amount} commission for this payment has been cancelled.")
                ->line("This does not affect commissions from other billing periods.")
                ->action('View your referrals', url('/portal/referrals')),

            default => $mail
                ->subject("Revenue QR: Commission update — \${$amount}")
                ->greeting('Commission Update')
                ->line("There was a change to your commission from **{$businessName}**.")
                ->action('View your referrals', url('/portal/referrals')),
        };
    }

    public function toArray(object $notifiable): array
    {
        $businessName = $this->commission->business?->name ?? 'A business';
        $amount = number_format((float) $this->commission->commission_amount, 2);

        $messages = [
            'refund' => "Your \${$amount} commission from {$businessName} was reversed due to a refund.",
            'dispute' => "Your \${$amount} commission from {$businessName} is on hold due to a payment dispute.",
            'dispute_won' => "Your \${$amount} commission from {$businessName} has been restored — dispute resolved.",
            'dispute_lost' => "Your \${$amount} commission from {$businessName} was cancelled — dispute lost.",
        ];

        $icons = [
            'refund' => '↩️',
            'dispute' => '⚠️',
            'dispute_won' => '✅',
            'dispute_lost' => '❌',
        ];

        return [
            'message' => $messages[$this->reason] ?? "Commission update for {$businessName}.",
            'action_url' => '/portal/referrals',
            'type' => 'referral_commission_reversed',
            'icon' => $icons[$this->reason] ?? 'ℹ️',
            'reason' => $this->reason,
            'commission_id' => $this->commission->id,
            'business_id' => $this->commission->business_id,
            'amount' => $this->commission->commission_amount,
        ];
    }
}
