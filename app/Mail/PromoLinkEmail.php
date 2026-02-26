<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use Illuminate\Contracts\Queue\ShouldQueue;

class PromoLinkEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $promotionName,
        public readonly string $businessName,
    ) {}

    public function build(): self
    {
        return $this->subject("Your promo from {$this->businessName}")
            ->view('emails.promo_link')
            ->text('emails.promo_link_plain');
    }
}


