<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.test')
            ->text('emails.test_plain');
    }
}


