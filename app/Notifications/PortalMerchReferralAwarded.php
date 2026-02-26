<?php

namespace App\Notifications;

use App\Models\MerchReferralReward;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PortalMerchReferralAwarded extends Notification
{
    use Queueable;

    public function __construct(public MerchReferralReward $reward, public int $count)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $desc = $this->reward->reward_description ?: 'Merch referral reward';
        $countText = $this->count === 1 ? '1 reward' : "{$this->count} rewards";
        return [
            'message' => "Merch referral earned: {$desc} ({$countText})",
            'action_url' => '/portal/merch',
            'type' => 'merch_referral_reward',
            'icon' => '🧢',
            'reward_id' => $this->reward->id,
            'count' => $this->count,
        ];
    }
}
