<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessInvoicePaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string,mixed> $details
     */
    public function __construct(public Business $business, public array $details)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoiceUrl = $this->details['hosted_invoice_url'] ?? null;

        $attemptCount = $this->details['attempt_count'] ?? 1;
        $nextAttempt = $this->details['next_payment_attempt'] ?? null;

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject('Revenue QR: Payment failed — action needed')
            ->greeting('Payment failed')
            ->line("We couldn't process your subscription payment for {$this->business->name} (attempt {$attemptCount}).")
            ->line('To avoid any interruption, please update your payment method in the billing portal.');

        if ($nextAttempt) {
            $mail->line("We'll automatically retry on **{$nextAttempt}**. Update your payment method before then to avoid service interruption.");
        }

        if (is_string($invoiceUrl) && $invoiceUrl !== '') {
            $mail->action('View invoice', $invoiceUrl);
        } else {
            $mail->action('Update payment method', url('/business/billing/portal'));
        }

        return $mail->line('If you need help, just reply to this email.');
    }
}

