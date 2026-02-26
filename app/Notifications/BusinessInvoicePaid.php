<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessInvoicePaid extends Notification implements ShouldQueue
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
        $amount = (float) ($this->details['amount'] ?? 0);
        $currency = strtoupper((string) ($this->details['currency'] ?? ''));
        $period = (string) ($this->details['period'] ?? '');
        $invoiceUrl = $this->details['hosted_invoice_url'] ?? null;

        $amountText = $currency ? "{$currency} " . number_format($amount, 2) : number_format($amount, 2);

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), config('app.name', 'Revenue QR'))
            ->subject("Revenue QR: Payment received ({$amountText})")
            ->greeting('Payment received')
            ->line("Business: {$this->business->name}")
            ->line("Amount: {$amountText}")
            ->line($period ? "Period: {$period}" : 'Period: (not provided)')
            ->action('Manage billing', url('/business/billing/portal'));

        if (is_string($invoiceUrl) && $invoiceUrl !== '') {
            $mail->line('You can view your invoice in Stripe:')->action('View invoice', $invoiceUrl);
        }

        return $mail->line('Thanks for using Revenue QR.');
    }
}

