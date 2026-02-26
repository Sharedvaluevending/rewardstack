<?php

namespace App\Notifications;

use App\Models\GameReward;
use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PortalLeaderboardPrize extends Notification
{
    use Queueable;

    public function __construct(
        public Leaderboard $leaderboard,
        public GameReward $reward,
        public LeaderboardEntry $entry
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $rank = $this->entry->rank ? "#{$this->entry->rank}" : 'a top spot';
        return [
            'message' => "Leaderboard prize awarded ({$rank}) — {$this->leaderboard->name}",
            'action_url' => '/portal/rewards/' . $this->reward->id,
            'type' => 'leaderboard_prize',
            'icon' => '🏆',
            'leaderboard_id' => $this->leaderboard->id,
            'reward_id' => $this->reward->id,
            'rank' => $this->entry->rank,
        ];
    }
}
