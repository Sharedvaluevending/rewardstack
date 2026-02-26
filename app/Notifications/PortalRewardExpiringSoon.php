<?php

namespace App\Notifications;

use App\Models\GameReward;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PortalRewardExpiringSoon extends Notification
{
    use Queueable;

    public function __construct(public GameReward $reward, public int $daysLeft)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $description = $this->reward->description ?: 'Reward';
        $expiresAt = $this->reward->expires_at?->format('M d, Y');
        $daysText = $this->daysLeft === 1 ? '1 day' : "{$this->daysLeft} days";

        return [
            'message' => "Reward expiring in {$daysText}: {$description}" . ($expiresAt ? " (ends {$expiresAt})" : ''),
            'action_url' => '/portal/rewards/' . $this->reward->id,
            'type' => 'reward_expiring',
            'icon' => '⏰',
            'reward_id' => $this->reward->id,
            'expires_at' => $this->reward->expires_at?->toDateString(),
        ];
    }
}
