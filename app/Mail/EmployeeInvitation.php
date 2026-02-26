<?php

namespace App\Mail;

use App\Models\EmployeeInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class EmployeeInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $inviteUrl;

    public function __construct(public EmployeeInvite $invite, ?string $inviteUrl = null)
    {
        $this->inviteUrl = $inviteUrl ?? $invite->url;
    }

    public function envelope(): Envelope
    {
        $business = $this->invite->business;
        $replyTo = [];
        if ($business?->email) {
            $replyTo = [new Address($business->email, $business->name ?? null)];
        }

        $clickTrackingHeader = json_encode([
            'filters' => [
                'clicktrack' => [
                    'settings' => [
                        'enable' => 0,
                        'enable_text' => false,
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        return new Envelope(
            subject: 'You have been invited to join ' . $this->invite->business->name,
            replyTo: $replyTo,
            using: [
                function (Email $message) use ($clickTrackingHeader) {
                    $message->getHeaders()->addTextHeader('X-SMTPAPI', $clickTrackingHeader);
                },
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee-invitation',
            with: [
                'inviteUrl' => $this->inviteUrl,
            ],
        );
    }
}
