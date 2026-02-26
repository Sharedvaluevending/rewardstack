<?php

namespace App\Notifications;

use App\Models\Promotion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PortalPunchCardCompleted extends Notification
{
    use Queueable;

    public function __construct(public Promotion $promotion)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = $this->promotion->name ?: 'Punch card';
        return [
            'message' => "Punch card completed: {$name}",
            'action_url' => '/portal/scans',
            'type' => 'punch_card_completed',
            'icon' => '✅',
            'promotion_id' => $this->promotion->id,
        ];
    }
}
