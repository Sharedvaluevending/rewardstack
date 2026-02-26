<?php

namespace App\Notifications;

use App\Models\UserPromoToken;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PortalPromoTokenAwarded extends Notification
{
    use Queueable;

    public function __construct(public UserPromoToken $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $promoName = $this->token->promotion?->name ?: 'Reward';
        return [
            'message' => "You won a reward: {$promoName}",
            'action_url' => '/promo/' . $this->token->code . '?source=portal',
            'type' => 'promo_token_awarded',
            'icon' => '🎁',
            'token_code' => $this->token->code,
            'promotion_id' => $this->token->promotion_id,
        ];
    }
}
