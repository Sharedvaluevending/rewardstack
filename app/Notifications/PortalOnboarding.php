<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PortalOnboarding extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Welcome! Tap here to learn how your portal works.',
            'action_url' => '/portal',
            'type' => 'portal_onboarding',
            'icon' => '✨',
        ];
    }
}
