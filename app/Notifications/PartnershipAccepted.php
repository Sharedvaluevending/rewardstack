<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Business;

class PartnershipAccepted extends Notification
{
    use Queueable;

    public function __construct(public Business $partner)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->partner->name} accepted your partnership request!",
            'action_url' => '/business/partnerships',
            'type' => 'partnership_accepted',
            'icon' => '✅',
        ];
    }
}
