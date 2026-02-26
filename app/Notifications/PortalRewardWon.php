<?php

namespace App\Notifications;

use App\Models\GameReward;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PortalRewardWon extends Notification
{
    use Queueable;

    public function __construct(public GameReward $reward)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $description = $this->reward->description ?: 'Reward';
        return [
            'message' => "You won a reward: {$description}",
            'action_url' => '/portal/rewards/' . $this->reward->id,
            'type' => 'reward_won',
            'icon' => '🎁',
            'reward_id' => $this->reward->id,
        ];
    }
}
