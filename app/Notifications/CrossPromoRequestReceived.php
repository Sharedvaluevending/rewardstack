<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Business;

class CrossPromoRequestReceived extends Notification
{
    use Queueable;

    public function __construct(public Business $requester, public string $promoName)
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
            'message' => "{$this->requester->name} sent a Partner Deal Chain request: {$this->promoName}",
            'action_url' => '/business/partnerships',
            'type' => 'cross_promo_request',
            'icon' => '🎁',
        ];
    }
}
