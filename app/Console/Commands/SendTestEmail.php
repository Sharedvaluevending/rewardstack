<?php

namespace App\Console\Commands;

use App\Mail\TestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'email:test {to : Recipient email address} {--subject= : Optional subject override}';

    protected $description = 'Send a test email using the current mail configuration';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $subject = (string) ($this->option('subject') ?: 'Email delivery test');

        Mail::to($to)->send(new TestEmail($subject));

        $this->info(sprintf(
            'Sent test email to %s (from: %s <%s>)',
            $to,
            (string) config('mail.from.name'),
            (string) config('mail.from.address'),
        ));

        return self::SUCCESS;
    }
}


