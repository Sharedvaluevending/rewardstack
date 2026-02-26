<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessSubscriptionActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Business $business,
        public SubscriptionPlan $plan,
        public string $billingPeriod = 'monthly'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->plan->name;
        $periodLabel = $this->billingPeriod === 'yearly' ? 'yearly' : 'monthly';

        return (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: {$planName} plan activated")
            ->greeting("You're all set!")
            ->line("Your {$planName} ({$periodLabel}) subscription is now active for {$this->business->name}.")
            ->action('Manage billing', url('/business/billing/portal'))
            ->line('If you have any questions, just reply to this email.');
    }
}

